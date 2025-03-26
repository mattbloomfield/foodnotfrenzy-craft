const q="modulepreload",j=function(e){return"/dist/"+e},I={},$=function(t,i,n){let o=Promise.resolve();if(i&&i.length>0){document.getElementsByTagName("link");const s=document.querySelector("meta[property=csp-nonce]"),a=(s==null?void 0:s.nonce)||(s==null?void 0:s.getAttribute("nonce"));o=Promise.allSettled(i.map(l=>{if(l=j(l),l in I)return;I[l]=!0;const f=l.endsWith(".css"),Q=f?'[rel="stylesheet"]':"";if(document.querySelector(`link[href="${l}"]${Q}`))return;const u=document.createElement("link");if(u.rel=f?"stylesheet":q,f||(u.as="script"),u.crossOrigin="",u.href=l,a&&u.setAttribute("nonce",a),document.head.appendChild(u),f)return new Promise((U,T)=>{u.addEventListener("load",U),u.addEventListener("error",()=>T(new Error(`Unable to preload CSS for ${l}`)))})}))}function r(s){const a=new Event("vite:preloadError",{cancelable:!0});if(a.payload=s,window.dispatchEvent(a),!a.defaultPrevented)throw s}return o.then(s=>{for(const a of s||[])a.status==="rejected"&&r(a.reason);return t().catch(r)})};var y={};const m=function(){return typeof window>"u"?"":getComputedStyle(document.documentElement).getPropertyValue("--breakpoint").trim().replace(/"/g,"")},H=function(){var e,t=250,i=m();function n(){var o=m();window.dispatchEvent(new CustomEvent("resized",{detail:{breakpoint:o}})),o!==i&&(window.A17&&(window.A17.currentMediaQuery=o),window.dispatchEvent(new CustomEvent("mediaQueryUpdated",{detail:{breakpoint:o,prevBreakpoint:i}})),i=o)}if(window.addEventListener("resize",function(){clearTimeout(e),e=setTimeout(n,t)}),typeof ResizeObserver=="function"){let o=null;new ResizeObserver(s=>{clearTimeout(o),o=setTimeout(()=>{window.A17.currentMediaQuery!==m()&&window.dispatchEvent(new Event("resize"))},t+1)}).observe(document.documentElement)}i===""?window.requestAnimationFrame(n):window.A17&&(window.A17.currentMediaQuery=i)},L=function(e,t){if(!e)return console.error("You need to pass a breakpoint name!"),!1;const i=new RegExp("\\+$|\\-$");let n=["xs","sm","md","lg","xl","xxl"];window.A17&&window.A17.breakpoints&&(Array.isArray(window.A17.breakpoints)?n=window.A17.breakpoints:console.warn("A17.breakpoints should be an array. Using defaults.")),t&&(Array.isArray(t)?n=t:console.warn("isBreakpoint breakpoints should be an array. Using defaults."));const o=m(),r=n.indexOf(o),s=i.exec(e),a=s?s[0]:!1,l=s?e.slice(0,-1):e,f=n.indexOf(l);return f<0?(console.warn("Unrecognized breakpoint. Supported breakpoints are: "+n.join(", ")),!1):a==="+"&&r>=f||a==="-"&&r<=f||!a&&e===o},R=function(e){for(var t in e)e.hasOwnProperty(t)&&delete e[t]};function B(e,t={}){if(!e||!(e instanceof Element))throw new Error("Node argument is required");return this.$node=e,this.options=Object.assign({intersectionOptions:{rootMargin:"20%"}},t.options||{}),this.__isEnabled=!1,this.__children=t.children,this.__breakpoints=t.breakpoints,this.__abortController=new AbortController,this.customMethodNames.forEach(i=>{this[i]=this.methods[i].bind(this)}),this._binds={},this._data=new Proxy(this._binds,{set:(i,n,o)=>(this.updateBinds(n,o),i[n]=o,!0)}),this.__isIntersecting=!1,this.__intersectionObserver=null,this}B.prototype=Object.freeze({updateBinds(e,t){this.$node.querySelectorAll("[data-"+this.name.toLowerCase()+"-bindel*="+e+"]").forEach(o=>{o.innerHTML=t}),this.$node.querySelectorAll("[data-"+this.name.toLowerCase()+'-bindattr*="'+e+':"]').forEach(o=>{o.dataset[this.name.toLowerCase()+"Bindattr"].split(",").forEach(s=>{s=s.split(":"),s[0]===e&&(s[1]==="class"?(this._binds[e]!==t&&o.classList.remove(this._binds[e]),t&&o.classList.add(t)):o.setAttribute(s[1],t))})})},init(){var t,i;const e=new RegExp("^data-"+this.name+"-(.*)","i");for(let n=0;n<this.$node.attributes.length;n++){const o=this.$node.attributes[n],r=e.exec(o.nodeName);r!=null&&r.length>=2&&(this.options[r[1]]&&console.warn(`Ignoring ${r[1]} option, as it already exists on the ${name} behavior. Please choose another name.`),this.options[r[1]]=o.value)}typeof((t=this.lifecycle)==null?void 0:t.init)=="function"&&this.lifecycle.init.call(this),typeof((i=this.lifecycle)==null?void 0:i.resized)=="function"&&(this.__resizedBind=this.__resized.bind(this),window.addEventListener("resized",this.__resizedBind)),(typeof this.lifecycle.mediaQueryUpdated=="function"||this.options.media)&&(this.__mediaQueryUpdatedBind=this.__mediaQueryUpdated.bind(this),window.addEventListener("mediaQueryUpdated",this.__mediaQueryUpdatedBind)),this.options.media?this.__toggleEnabled():this.enable(),this.__intersections()},destroy(){var e;this.__abortController.abort(),this.__isEnabled===!0&&this.disable(),typeof((e=this.lifecycle)==null?void 0:e.destroy)=="function"&&this.lifecycle.destroy.call(this),typeof this.lifecycle.resized=="function"&&window.removeEventListener("resized",this.__resizedBind),(typeof this.lifecycle.mediaQueryUpdated=="function"||this.options.media)&&window.removeEventListener("mediaQueryUpdated",this.__mediaQueryUpdatedBind),(this.lifecycle.intersectionIn!=null||this.lifecycle.intersectionOut!=null)&&(this.__intersectionObserver.unobserve(this.$node),this.__intersectionObserver.disconnect()),R(this)},getChild(e,t,i=!1){let n;return this.__children!=null&&this.__children[e]!=null?n=this.__children[e]:e instanceof NodeList?(n=e,i=!0):e instanceof Element||e instanceof HTMLDocument||e===window?(n=e,i=!1):(t==null&&(t=this.$node),n=t[i?"querySelectorAll":"querySelector"]("[data-"+this.name.toLowerCase()+"-"+e.toLowerCase()+"]")),i&&(n==null?void 0:n.length)>0?(n.on=(o,r,s)=>{n.forEach(a=>{this.__on(a,o,r,s)})},n.off=(o,r)=>{n.forEach(s=>{this.__off(s,o,r)})},n.forEach(o=>{o.on=o.on?o.on:(r,s,a)=>{this.__on(o,r,s,a)},o.off=o.off?o.off:(r,s)=>{this.__off(o,r,s)}})):n&&(n.on=n.on?n.on:(o,r,s)=>{this.__on(n,o,r,s)},n.off=n.off?n.off:(o,r)=>{this.__off(n,o,r)}),n},getChildren(e,t){return this.getChild(e,t,!0)},isEnabled(){return this.__isEnabled},enable(){this.__isEnabled=!0,typeof this.lifecycle.enabled=="function"&&this.lifecycle.enabled.call(this)},disable(){this.__isEnabled=!1,typeof this.lifecycle.disabled=="function"&&this.lifecycle.disabled.call(this)},addSubBehavior(e,t=this.$node,i={}){const n=d;typeof e=="string"?n.initBehavior(e,t,i):(n.add(e),n.initBehavior(e.prototype.behaviorName,t,i))},isBreakpoint(e){return L(e,this.__breakpoints)},__on(e,t,i,n){typeof n=="boolean"&&n===!0&&(n={passive:!0});const o={signal:this.__abortController.signal,...n};e.attachedListeners||(e.attachedListeners={}),Object.values(e.attachedListeners).find(s=>s.type===t&&s.fn===i)||(e.attachedListeners[Object.values(e.attachedListeners).length]={type:t,fn:i},e.addEventListener(t,i,o))},__off(e,t,i){e.attachedListeners?Object.keys(e.attachedListeners).forEach(n=>{const o=e.attachedListeners[n];(!t&&!i||t===o.type&&!i||t===o.type&&i===o.fn)&&(delete e.attachedListeners[n],e.removeEventListener(o.type,o.fn))}):e.removeEventListener(t,i)},__toggleEnabled(){const e=L(this.options.media,this.__breakpoints);e&&!this.__isEnabled?this.enable():!e&&this.__isEnabled&&this.disable()},__mediaQueryUpdated(e){var t;typeof((t=this.lifecycle)==null?void 0:t.mediaQueryUpdated)=="function"&&this.lifecycle.mediaQueryUpdated.call(this,e),this.options.media&&this.__toggleEnabled()},__resized(e){var t;typeof((t=this.lifecycle)==null?void 0:t.resized)=="function"&&this.lifecycle.resized.call(this,e)},__intersections(){(this.lifecycle.intersectionIn!=null||this.lifecycle.intersectionOut!=null)&&(this.__intersectionObserver=new IntersectionObserver(e=>{e.forEach(t=>{t.target===this.$node&&(t.isIntersecting?!this.__isIntersecting&&typeof this.lifecycle.intersectionIn=="function"&&(this.__isIntersecting=!0,this.lifecycle.intersectionIn.call(this)):this.__isIntersecting&&typeof this.lifecycle.intersectionOut=="function"&&(this.__isIntersecting=!1,this.lifecycle.intersectionOut.call(this)))})},this.options.intersectionOptions),this.__intersectionObserver.observe(this.$node))}});const O=(e,t={},i={})=>{const n=function(...a){B.apply(this,a)},o=[],r={name:{get(){return this.behaviorName}},behaviorName:{value:e,writable:!0},lifecycle:{value:i},methods:{value:t},customMethodNames:{value:o}};return Object.keys(t).forEach(a=>{o.push(a)}),n.prototype=Object.create(B.prototype,r),n};let h={dataAttr:"behavior",lazyAttr:"behavior-lazy",intersectionOptions:{rootMargin:"20%"},breakpoints:["xs","sm","md","lg","xl","xxl"]},p=[],z=!1;const E={},c=new Map,w=new Map;let b;const v=new Map,g=new Map;function S(e,t){return t=t.toLowerCase().replace(/-([a-zA-Z0-9])/ig,(i,n)=>n.toUpperCase()),e.dataset&&e.dataset[t]?e.dataset[t].split(" ").filter(i=>i):[]}function A(e){const t=p.indexOf(e);t>-1&&p.splice(t,1)}function V(e,t){const i=c.get(t);if(!i||!i[e]){console.warn(`No behavior '${e}' instance on:`,t);return}i[e].destroy(),delete i[e],Object.keys(i).length===0&&c.delete(t)}function F(e){const t=Array.from(c.keys());t.push(e),t.forEach(i=>{if(e===i||e.contains(i)){const n=c.get(i);n&&Object.keys(n).forEach(o=>{V(o,i),b.unobserve(i),v.delete(i),g.delete(i)})}})}function D(e,t){if(p.indexOf(e)>-1){const i=w.get(e)||[];i.includes(t)||i.push(t),w.set(e,i);return}p.push(e);try{$(()=>import(`${y.BEHAVIORS_PATH}/${(y.BEHAVIORS_COMPONENT_PATHS[e]||"").replace(/^\/|\/$/ig,"")}/${e}.${y.BEHAVIORS_EXTENSION}`),[]).then(i=>{M(e,t,i)}).catch(i=>{console.warn(`No loaded behavior called: ${e}`),A(e)})}catch{try{$(()=>import(`${y.BEHAVIORS_PATH}/${e}.${y.BEHAVIORS_EXTENSION}`),[]).then(n=>{M(e,t,n)}).catch(n=>{console.warn(`No loaded behavior called: ${e}`),A(e)})}catch{console.warn(`Unknown behavior called: ${e}. 
It maybe the behavior doesn't exist, check for typos and check Webpack has generated your file. 
If you are using dynamically imported behaviors, you may also want to check your webpack config. See https://github.com/area17/a17-behaviors/wiki/Setup#webpack-config`),A(e)}}}function M(e,t,i){i.default&&typeof i.default=="function"?(E[e]=i.default,_(e,t),w.get(e)&&(w.get(e).forEach(n=>{_(e,n)}),w.delete(e))):(console.warn(`Tried to import ${e}, but it seems to not be a behavior`),A(e))}function C(e){if(!("querySelectorAll"in e))return;[e,...e.querySelectorAll(`[data-${h.dataAttr}]`)].forEach(n=>{const o=S(n,h.dataAttr);o&&o.forEach(r=>{_(r,n)})}),[e,...e.querySelectorAll(`[data-${h.lazyAttr}]`)].forEach(n=>{const o=S(n,h.lazyAttr),r=new Map;o.forEach(s=>{const a=n.dataset[`${s.toLowerCase()}Lazymedia`];r.set(s,a||!1)}),n!==document&&(v.set(n,r),g.set(n,!1),b.observe(n))})}function W(){z=!0,new MutationObserver(t=>{t.forEach(i=>{i.removedNodes.forEach(n=>{F(n)}),i.addedNodes.forEach(n=>{C(n)})})}).observe(document.body,{childList:!0,subtree:!0,attributes:!1,characterData:!1})}function x(e){e.forEach(t=>{if(g.get(t)!==void 0&&g.get(t)===!1)return;let i=v.get(t);i&&i.forEach((n,o)=>{(!n||L(n,h.breakpoints))&&(_(o,t),i.delete(o),i.size===0?(b.unobserve(t),v.delete(t)):v.set(t,i))})})}function Z(e){e.forEach(t=>{t.isIntersecting?(g.set(t.target,!0),x([t.target])):g.set(t.target,!1)})}function G(){x(Array.from(v.keys()))}function _(e,t,i={}){if(!E[e]){D(e,t);return}i={breakpoints:h.breakpoints,...i};const n=c.get(t)||{};if(Object.keys(n).length===0||!n[e]){const o=new E[e](t,i);n[e]=o,c.set(t,n);try{return o.init(),o}catch(r){console.log(`Error in behavior '${e}' on:`,t),console.log(r)}}}function P(e){typeof e=="function"&&e.prototype.behaviorName&&(e={[e.prototype.behaviorName]:e}),typeof e=="string"&&arguments.length>1&&(e={[e]:O(...arguments)});const t=Object.keys(e).filter(i=>p.indexOf(i)===-1);t.length&&(p=p.concat(t),t.forEach(i=>{E[i]=e[i]}))}function X(e){const t=c.get(e);if(!t)console.warn("No behaviors on:",e);else return t}function Y(e,t){const i=c.get(t);if(!i||!i[e])console.warn(`No behavior '${e}' instance on:`,t);else return c.get(t)[e]}function k(e,t,i,n){const o=c.get(t);if(!o||!o[e])console.warn(`No behavior '${e}' instance on:`,t);else if(c.get(t)[e][i]){if(n&&typeof n=="function")return c.get(t)[e][i];if(n)c.get(t)[e][i]=n;else return c.get(t)[e][i]}else console.warn(`No property '${i}' in behavior '${e}' instance on:`,t)}function J(e,t={}){h={...h,...t},H(),b=new IntersectionObserver(Z,h.intersectionOptions),e&&P(e),C(document),z||W(),window.addEventListener("mediaQueryUpdated",G)}function K(){arguments&&(P.apply(null,arguments),C(document))}let d={init:J,add:K,initBehavior:_,get currentBreakpoint(){return m()}};try{y.MODE==="development"&&(Object.defineProperty(d,"loaded",{get:()=>p}),d.activeBehaviors=c,d.active=c,d.getBehaviors=X,d.getProps=Y,d.getProp=k,d.setProp=k,d.callMethod=k)}catch{}const N=O("cookingMode",{enterCookingMode(){document.body.classList.add("cooking-mode");const e=document.createElement("div");e.className="recipe-content-wrapper";const t=document.querySelector("h1").cloneNode(!0),i=document.querySelector(".recipe-content").cloneNode(!0);e.appendChild(t),e.appendChild(i),document.body.appendChild(e),this.preventSleep(),this.setupScrollProgress(e)},exitCookingMode(){document.body.classList.remove("cooking-mode");const e=document.querySelector(".recipe-content-wrapper");e&&e.remove(),this.allowSleep()},preventSleep(){"wakeLock"in navigator?navigator.wakeLock.request("screen").then(e=>{this.wakeLock=e,console.log("Wake Lock is active"),document.addEventListener("visibilitychange",this.handleVisibilityChange)}).catch(e=>{console.error(`Wake Lock error: ${e.name}, ${e.message}`),this.useFallbackSleepPrevention()}):this.useFallbackSleepPrevention()},useFallbackSleepPrevention(){const e=document.createElement("video");e.setAttribute("id","sleep-prevention-video"),e.setAttribute("src","data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAAAFhtZGF0AAAAMGRhdGEAAAAPAAAAAQAAAQAAAAEAAAJYbGliZmFhYyAxLjI4AAAAAAAAAAAAAAAAAAAAAAIAre7u7g=="),e.setAttribute("loop",""),e.style.display="none",document.body.appendChild(e),e.play().catch(t=>console.error("Fallback sleep prevention failed:",t))},allowSleep(){typeof this.wakeLock<"u"&&this.wakeLock!==null&&(this.wakeLock.release().then(()=>{console.log("Wake Lock released"),this.wakeLock=null}),document.removeEventListener("visibilitychange",this.handleVisibilityChange));const e=document.getElementById("sleep-prevention-video");e&&(e.pause(),e.remove())},handleVisibilityChange(){document.visibilityState==="visible"&&wakeLock===null&&this.preventSleep()},setupScrollProgress(e){e.addEventListener("scroll",()=>{const t=e.scrollHeight-e.clientHeight,i=e.scrollTop/t*100;this.progressBar.style.width=i+"%"})}},{init(){this.wakeLock=null;const e=document.createElement("button");e.className="cooking-mode-btn",e.innerHTML=`
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 3v3a2 2 0 0 1-2 2H3"></path>
                    <path d="M21 8h-3a2 2 0 0 1-2-2V3"></path>
                    <path d="M3 16h3a2 2 0 0 1 2 2v3"></path>
                    <path d="M16 21v-3a2 2 0 0 1 2-2h3"></path>
                </svg>
                Cooking Mode
            `,document.body.appendChild(e);const t=document.createElement("button");t.className="exit-cooking-mode",t.textContent="Exit Cooking Mode",document.body.appendChild(t);const i=document.createElement("div");i.className="progress-bar",document.body.appendChild(i),e.addEventListener("click",()=>{this.enterCookingMode()}),t.addEventListener("click",()=>{this.exitCookingMode()}),document.addEventListener("keydown",n=>{n.key==="Escape"&&document.body.classList.contains("cooking-mode")&&this.exitCookingMode()})}}),ee=O("walmartIntegration",{addCartIcon(e){const t=e.querySelector("[data-ingredient-name]").textContent,i=`https://www.walmart.com/search?q=${encodeURIComponent(t)}`;this.$node.querySelector("[data-cart-icon]").addEventListener("click",function(){window.open(i,"_blank")})}},{init(){this.addCartIcon(this.$node)}}),te=Object.freeze(Object.defineProperty({__proto__:null,cookingMode:N,walmartIntegration:ee},Symbol.toStringTag,{value:"Module"}));window.A17=window.A17||{};window.A17.behaviors=d;window.A17.behaviors.init(te,{breakpoints:[]});
