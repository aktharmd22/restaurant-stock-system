import{c}from"./boxes-DUCB2JCu.js";import{H as v,p as C,o as a,f as y,a as l,c as u,r as _,i as f,q as z,t as k,u as d,e as M,g as w,F as B,B as I,z as j}from"./app-CuC7CfS5.js";/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const E=c("circle-check",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const T=c("circle-x",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m15 9-6 6",key:"1uzhvr"}],["path",{d:"m9 9 6 6",key:"z0biqf"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const P=c("eye-off",[["path",{d:"M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49",key:"ct8e1f"}],["path",{d:"M14.084 14.158a3 3 0 0 1-4.242-4.242",key:"151rxh"}],["path",{d:"M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143",key:"13bj9a"}],["path",{d:"m2 2 20 20",key:"1ooewy"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const S=c("eye",[["path",{d:"M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0",key:"1nclc0"}],["circle",{cx:"12",cy:"12",r:"3",key:"1v7zrd"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const N=c("info",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M12 16v-4",key:"1dtifu"}],["path",{d:"M12 8h.01",key:"e9boi3"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const V=c("x",[["path",{d:"M18 6 6 18",key:"1bl5f8"}],["path",{d:"m6 6 12 12",key:"d8bk6v"}]]),m=v({items:[]});let q=1;function p({message:t,type:e="info",duration:r=4e3,action:n=null}){const i=q++;return m.items.push({id:i,message:t,type:e,action:n}),r>0&&setTimeout(()=>x(i),r),i}function x(t){const e=m.items.findIndex(r=>r.id===t);e!==-1&&m.items.splice(e,1)}function D(){return{toasts:m.items,push:p,dismiss:x,success:(t,e={})=>p({...e,message:t,type:"success"}),error:(t,e={})=>p({...e,message:t,type:"error"}),info:(t,e={})=>p({...e,message:t,type:"info"})}}const F={class:"flex-1 text-body text-ink"},H=["onClick"],L=["onClick"],A={__name:"ToastHost",props:{raised:{type:Boolean,default:!1}},setup(t){const{toasts:e,push:r,dismiss:n}=D(),i=j(),h={success:E,error:T,info:N},b={success:"border-approved/25 text-approved",error:"border-rejected/25 text-rejected",info:"border-primary/25 text-primary"};return C(()=>i.props.flash,o=>{o&&(o.success&&r({message:o.success,type:"success"}),o.error&&r({message:o.error,type:"error",duration:7e3}),o.info&&r({message:o.info,type:"info"}))},{immediate:!0,deep:!0}),(o,X)=>(a(),y(I,{to:"body"},[l("div",{class:f(["pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 px-4 pb-4 pb-safe sm:items-end",t.raised?"mb-[72px] sm:mb-0":""]),role:"status","aria-live":"polite"},[(a(!0),u(B,null,_(d(e),s=>(a(),u("div",{key:s.id,class:f(["animate-toast-in pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-card border bg-surface p-4 shadow-float",b[s.type]])},[(a(),y(z(h[s.type]),{size:20,class:"mt-0.5 shrink-0","aria-hidden":"true"})),l("p",F,k(s.message),1),s.action?(a(),u("button",{key:0,type:"button",class:"min-h-touch shrink-0 rounded-control px-3 text-body font-medium text-primary hover:bg-primary-light",onClick:g=>{s.action.onClick(),d(n)(s.id)}},k(s.action.label),9,H)):M("",!0),l("button",{type:"button",class:"flex h-8 w-8 shrink-0 items-center justify-center rounded-control text-ink-muted hover:text-ink","aria-label":"Close",onClick:g=>d(n)(s.id)},[w(d(V),{size:18})],8,L)],2))),128))],2)]))}};export{E as C,P as E,N as I,V as X,A as _,S as a,T as b,D as u};
