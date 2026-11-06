(function(){
'use strict';
var cfg=window.BIGSPY_INTERNAL_LINK_HISTORY;if(!cfg||!cfg.ajaxUrl)return;
var legacyKey='bigspyInternalLinkHistory:v1',history=[],timer=0,loaded=false;
function norm(label,url){label=String(label||'').trim();url=String(url||'').trim();if(!label||!url)return null;try{var p=new URL(url,location.origin);if(!/^https?:$/.test(p.protocol)||p.hostname.toLowerCase()!==String(cfg.siteHost).toLowerCase())return null;return{label:label.slice(0,200),url:p.href};}catch(e){return null;}}
function uniq(items){var out=[],seen={};(items||[]).forEach(function(x){var p=norm(x&&(x.label||x.name),x&&x.url);if(!p)return;var k=p.label.toLowerCase()+'\n'+p.url.toLowerCase();if(!seen[k]){seen[k]=1;out.push(p);}});return out.slice(0,100);}
function rows(){var out=[];document.querySelectorAll('input[id^="bigspy-internal-label-"]').forEach(function(l){var u=document.getElementById('bigspy-internal-url-'+l.id.replace('bigspy-internal-label-',''));if(u)out.push({label:l,url:u});});document.querySelectorAll('.bigspy-pw-panel[data-type="internal"] .bigspy-pw-row').forEach(function(r){var l=r.querySelector('.bigspy-pw-name'),u=r.querySelector('.bigspy-pw-url');if(l&&u)out.push({label:l,url:u});});return out;}
function esc(v){var d=document.createElement('div');d.textContent=String(v||'');return d.innerHTML.replace(/"/g,'&quot;');}
function api(method,items){var d=new URLSearchParams();d.set('action','bigspy_internal_link_history');d.set('nonce',cfg.nonce);d.set('method',method);if(items)d.set('items',JSON.stringify(items));return fetch(cfg.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:d.toString()}).then(function(r){return r.json();}).then(function(r){if(!r||!r.success)throw new Error('History request failed');return r.data.items||[];});}
function lists(){return{label:document.getElementById('bigspy-internal-label-history')||document.getElementById('bigspy-pw-internal-name-history'),url:document.getElementById('bigspy-internal-url-history')||document.getElementById('bigspy-pw-internal-url-history')};}
// Only the Generator's empty rows are filled; Post Writer rows are prefilled by the server on the same page.
function isGen(r){return /^bigspy-internal-label-/.test(r.label.id);}
function generatorRows(){return rows().filter(isGen);}
function hydrateRows(){var g=generatorRows();if(!history.length||g.some(function(r){return r.label.value.trim()||r.url.value.trim();}))return;g.slice(0,history.length).forEach(function(r,i){r.label.value=history[i].label;r.url.value=history[i].url;});}
function render(){var l=lists();if(l.label)l.label.innerHTML=history.map(function(x){return'<option value="'+esc(x.label)+'"></option>';}).join('');if(l.url)l.url.innerHTML=history.map(function(x){return'<option value="'+esc(x.url)+'"></option>';}).join('');}
function persist(items){history=uniq(items);render();return api('save',history).then(function(x){history=uniq(x);render();return history;});}
function match(target){var row=rows().filter(function(r){return r.label===target||r.url===target;})[0];if(!row)return;var val=target.value.trim(),p;if(target===row.label){p=history.filter(function(x){return x.label===val;})[0];if(p)row.url.value=p.url;}else{p=history.filter(function(x){return x.url===val;})[0];if(p)row.label.value=p.label;}}
// The rows are the saved list: the form edited last (Generator or Post Writer) replaces it, so clearing a row removes that link.
function saveSoon(source){if(!loaded)return;clearTimeout(timer);timer=setTimeout(function(){persist(rows().filter(function(r){return isGen(r)===source;}).map(function(r){return norm(r.label.value,r.url.value);}).filter(Boolean));},400);}
function rowOf(t){return rows().filter(function(r){return r.label===t||r.url===t;})[0];}
document.addEventListener('input',function(e){match(e.target);var r=rowOf(e.target);if(r)saveSoon(isGen(r));});document.addEventListener('change',function(e){var r=rowOf(e.target);if(r)saveSoon(isGen(r));});
document.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('.bigspy-pw-panel[data-type="internal"] .bigspy-pw-remove'))saveSoon(false);});
api('list').then(function(server){var legacy=[];try{legacy=JSON.parse(localStorage.getItem(legacyKey)||'[]');}catch(e){}history=uniq(server.concat(legacy));loaded=true;if(legacy.length){try{localStorage.removeItem(legacyKey);}catch(e){}persist(history).then(hydrateRows);}else{render();hydrateRows();}}).catch(function(){loaded=true;render();hydrateRows();});
}());