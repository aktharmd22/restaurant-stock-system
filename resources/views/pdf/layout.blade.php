<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /*
         * Every PDF the app produces shares this. dompdf has no web fonts, so
         * DejaVu it is - which matters more than it looks: it is the only font
         * bundled that carries the rupee sign.
         *
         * A printed page is read at arm's length on a loading bay, so the type
         * is a little larger than the screen and the rules are a little
         * heavier than they would be on a monitor.
         */
        @page { margin: 16mm 14mm 20mm 14mm; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #16181D;
            margin: 0;
        }

        /* ---------------------------------------------------------- header */

        .masthead { width: 100%; margin-bottom: 10px; }
        .masthead td { vertical-align: bottom; padding: 0; border: none; }

        .business { font-size: 15pt; font-weight: bold; letter-spacing: -0.2pt; }
        .tagline { font-size: 8.5pt; color: #6B7280; margin-top: 1px; }

        .doc-type {
            font-size: 8pt;
            letter-spacing: 1pt;
            text-transform: uppercase;
            color: #6B7280;
        }
        .doc-number { font-size: 13pt; font-weight: bold; margin-top: 1px; }
        .doc-date { font-size: 8.5pt; color: #6B7280; margin-top: 1px; }

        .rule { border-bottom: 2px solid #0F1D40; margin-bottom: 12px; }

        /* ------------------------------------------------------- fact strip */

        .facts { width: 100%; background: #F5F6F8; margin-bottom: 14px; }
        .facts td {
            padding: 7px 10px;
            border: none;
            border-right: 1px solid #FFFFFF;
            vertical-align: top;
        }
        .facts td:last-child { border-right: none; }
        .facts .label {
            font-size: 7.5pt;
            letter-spacing: 0.5pt;
            text-transform: uppercase;
            color: #6B7280;
        }
        .facts .value { font-size: 10.5pt; font-weight: bold; margin-top: 1px; }

        /* ------------------------------------------------------------ table */

        table.grid { width: 100%; border-collapse: collapse; }

        table.grid th {
            text-align: left;
            font-size: 8pt;
            font-weight: normal;
            letter-spacing: 0.4pt;
            text-transform: uppercase;
            color: #6B7280;
            border-bottom: 1px solid #C9CDD4;
            padding: 5px 6px;
        }

        table.grid td {
            padding: 7px 6px;
            border-bottom: 1px solid #EDEFF2;
            vertical-align: top;
        }

        table.grid tr.group td {
            background: #F5F6F8;
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 0.4pt;
            text-transform: uppercase;
            color: #3D4350;
            padding: 5px 6px;
            border-bottom: 1px solid #C9CDD4;
        }

        .right { text-align: right; }
        .num { font-weight: bold; }
        .muted { color: #6B7280; }
        .empty { color: #6B7280; padding: 14px 6px; }

        /* A box to tick with a pen, walking the store. */
        .tick {
            display: block;
            width: 11px;
            height: 11px;
            border: 1px solid #9AA0A6;
        }

        /* ----------------------------------------------------------- totals */

        .totals { margin-top: 14px; width: 100%; }
        .totals td { padding: 4px 6px; border: none; }
        .totals .label { color: #6B7280; }
        .totals .value { text-align: right; font-weight: bold; }

        /* -------------------------------------------------------- signature */

        .sign { width: 100%; margin-top: 26px; }
        .sign td { width: 50%; padding: 0 18px 0 0; border: none; vertical-align: top; }
        .sign td:last-child { padding: 0 0 0 18px; }
        .sign .line { border-bottom: 1px solid #9AA0A6; height: 34px; }
        .sign .who { font-size: 8.5pt; color: #6B7280; margin-top: 4px; }

        .note { margin-top: 16px; font-size: 8.5pt; color: #6B7280; }

        /* ----------------------------------------------------------- footer */

        .foot {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            font-size: 7.5pt;
            color: #9AA0A6;
            border-top: 1px solid #EDEFF2;
            padding-top: 4px;
        }
        .foot table { width: 100%; }
        .foot td { border: none; padding: 0; }
        .foot .page:after { content: counter(page) " of " counter(pages); }
    </style>
</head>
<body>
    <div class="foot">
        <table>
            <tr>
                <td>{{ $business }} &middot; made {{ now()->format('j M Y, g:i a') }}</td>
                <td class="right">Page <span class="page"></span></td>
            </tr>
        </table>
    </div>

    <table class="masthead">
        <tr>
            <td>
                <div class="business">{{ $business }}</div>
                @if (! empty($tagline))
                    <div class="tagline">{{ $tagline }}</div>
                @endif
            </td>
            <td class="right">
                <div class="doc-type">{{ $docType }}</div>
                @if (! empty($docNumber))
                    <div class="doc-number">{{ $docNumber }}</div>
                @endif
                @if (! empty($docDate))
                    <div class="doc-date">{{ $docDate }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    @yield('body')
</body>
</html>
