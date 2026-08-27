/**
 * Bundled by jsDelivr using Rollup v4.62.2 and esbuild v0.28.1.
 * Original file: /npm/preact@10.29.7/compat/client.mjs
 *
 * Do NOT use SRI with dynamically generated files! More information: https://www.jsdelivr.com/using-sri-with-dynamic-files
 */
import{unmountComponentAtNode as r,render as u,hydrate as d}from"preact/compat";function o(t){return{render:function(n){u(n,t)},unmount:function(){r(t)}}}function e(t,n){return d(n,t),o(t)}var f={createRoot:o,hydrateRoot:e};export{o as createRoot,f as default,e as hydrateRoot};
