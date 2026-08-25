<?php

use App\Enums\RoleName;
use App\Events\RequestEvent;
use App\Notifications\RequestNotification;
use App\Services\AlertService;
use App\Services\Requests\RequestWorkflowService;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedRoles();

    $this->workflow = app(RequestWorkflowService::class);
    $this->main = mainBranch();
    $this->park = subBranch('PARK');

    $this->onion = kgItem('Onion');
    giveStock($this->main, $this->onion, 100);

    $this->staff = userWithRole(RoleName::BranchManager, $this->park, ['phone' => '9000000101']);
    $this->admin = userWithRole(RoleName::MainAdmin, $this->main, ['phone' => '9000000102']);
});

it('tells the main store two rising chimes when a branch asks', function () {
    Event::fake([RequestEvent::class]);
    Notification::fake();

    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 5],
    ]);

    Event::assertDispatched(RequestEvent::class, function (RequestEvent $event) {
        return $event->sound === AlertService::SOUND_NEW_REQUEST
            && $event->channelName === 'admin.main'
            && str_contains($event->message, 'PARK Branch');
    });

    // And a written record, because a sound can be missed.
    Notification::assertSentTo($this->admin, RequestNotification::class);
});

it('plays a different sound for approved, cut and refused', function () {
    Event::fake([RequestEvent::class]);

    // Approved in full.
    $full = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);
    $this->workflow->approveAll($full, $this->admin);

    Event::assertDispatched(RequestEvent::class, fn (RequestEvent $e) => $e->sound === AlertService::SOUND_APPROVED
        && $e->channelName === "branch.{$this->park->id}");

    // Cut down.
    $partial = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approve($partial, $this->admin, [
        $partial->lines->first()->id => ['qty' => 4, 'reason_code' => 'out_of_stock'],
    ]);

    Event::assertDispatched(RequestEvent::class, fn (RequestEvent $e) => $e->sound === AlertService::SOUND_PARTIAL);

    // Refused outright.
    $rejected = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 3]]);
    $this->workflow->approve($rejected, $this->admin, [
        $rejected->lines->first()->id => ['qty' => 0, 'reason_code' => 'not_needed_today'],
    ]);

    Event::assertDispatched(RequestEvent::class, fn (RequestEvent $e) => $e->sound === AlertService::SOUND_REJECTED);
});

it('tells the branch when the goods leave, and the store when they arrive', function () {
    Event::fake([RequestEvent::class]);

    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);
    $this->workflow->approveAll($request, $this->admin);
    $this->workflow->dispatch($request->fresh(), $this->admin);

    Event::assertDispatched(RequestEvent::class, fn (RequestEvent $e) => $e->sound === AlertService::SOUND_SENT
        && $e->channelName === "branch.{$this->park->id}");

    $this->workflow->receive($request->fresh(), $this->staff);

    Event::assertDispatched(RequestEvent::class, fn (RequestEvent $e) => $e->channelName === 'admin.main'
        && str_contains($e->message, 'confirmed'));
});

it('carries a link straight to the thing that changed', function () {
    Event::fake([RequestEvent::class]);

    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);

    Event::assertDispatched(RequestEvent::class, fn (RequestEvent $e) => $e->url === "/admin/requests?selected={$request->id}");
});

it('counts what is waiting so the tab can show it', function () {
    $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertInertia(fn ($page) => $page->where('alerts.pending', 1)->where('alerts.unread', 1));
});

it('stops counting once the admin has opened it', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);

    $this->actingAs($this->admin)->get("/admin/requests?selected={$request->id}");

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertInertia(fn ($page) => $page->where('alerts.unread', 0));
});

it('lets a branch turn the sound off for themselves only', function () {
    $this->actingAs($this->staff)
        ->put('/settings/sound', ['sound_enabled' => false, 'sound_volume' => 30])
        ->assertSessionHas('success', 'Sound is off.');

    expect($this->staff->fresh()->sound_enabled)->toBeFalse()
        ->and($this->staff->fresh()->sound_volume)->toBe(30)
        ->and($this->admin->fresh()->sound_enabled)->toBeTrue();
});

it('will not accept a silly volume', function () {
    $this->actingAs($this->staff)
        ->put('/settings/sound', ['sound_enabled' => true, 'sound_volume' => 500])
        ->assertSessionHasErrors('sound_volume');
});

/*
|--------------------------------------------------------------------------
| The half-hour backstop
|--------------------------------------------------------------------------
*/

it('messages a phone about a request nobody has opened in half an hour', function () {
    $sms = Mockery::mock(SmsSender::class);
    $sms->shouldReceive('send')
        ->once()
        ->withArgs(fn (string $phone, string $message) => $phone === '9000000102'
            && str_contains($message, 'PARK Branch')
            && str_contains($message, 'waiting'));
    $this->app->instance(SmsSender::class, $sms);

    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);
    $request->forceFill(['submitted_at' => now()->subMinutes(45)])->saveQuietly();

    $this->artisan('requests:escalate')->assertSuccessful();

    expect($request->fresh()->escalated_at)->not->toBeNull();
});

it('leaves a fresh request alone', function () {
    $sms = Mockery::mock(SmsSender::class);
    $sms->shouldNotReceive('send');
    $this->app->instance(SmsSender::class, $sms);

    $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);

    $this->artisan('requests:escalate')->assertSuccessful();
});

it('never messages twice about the same request', function () {
    $sms = Mockery::mock(SmsSender::class);
    $sms->shouldReceive('send')->once();
    $this->app->instance(SmsSender::class, $sms);

    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);
    $request->forceFill(['submitted_at' => now()->subMinutes(45)])->saveQuietly();

    $this->artisan('requests:escalate');
    $this->artisan('requests:escalate');
});

it('stops chasing a request once it has been approved', function () {
    $sms = Mockery::mock(SmsSender::class);
    $sms->shouldNotReceive('send');
    $this->app->instance(SmsSender::class, $sms);

    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);
    $request->forceFill(['submitted_at' => now()->subMinutes(45)])->saveQuietly();
    $this->workflow->approveAll($request->fresh(), $this->admin);

    $this->artisan('requests:escalate')->assertSuccessful();
});
