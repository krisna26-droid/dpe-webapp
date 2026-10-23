const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname,'../../public/assets/dpe/own-account.js'),'utf8');
function element(attrs={}) { return { attrs,hidden:true,events:{},type:'password',files:[],setAttribute(k,v){this.attrs[k]=v;},getAttribute(k){return this.attrs[k]||null;},addEventListener(k,v){this.events[k]=v;},setCustomValidity(v){this.validity=v;} }; }
function setup(photo=true) {
 const button=element({'data-account-toggle':'password','data-account-label':'kata sandi baru'}),input=element(),upload=element(),preview=element({src:'/teacher/profile/photo'}),hint=element();preview.hidden=false;button.textContent='eye-svg';
 const revoked=[],created=[],events={};
 const document={querySelectorAll(s){return s==='[data-account-toggle]'?[button]:[preview];},getElementById(){return input;},querySelector(s){return !photo?null:s.includes('preview')?preview:s.includes('hint')?hint:upload;}};
 vm.runInNewContext(source,{document,window:{addEventListener(k,v){events[k]=v;}},URL:{createObjectURL(f){created.push(f);return 'blob:'+created.length;},revokeObjectURL(u){revoked.push(u);}}});
 return {button,input,upload,preview,hint,revoked,created,events,select(file){upload.files=file?[file]:[];upload.events.change();}};
}
test('password can be shown and hidden without submitting, replacing its icon or changing its value',()=>{const s=setup(false);s.input.value='UserSecret123!';assert.equal(s.button.hidden,false);s.button.events.click();assert.equal(s.input.type,'text');assert.equal(s.button.attrs['aria-pressed'],'true');assert.equal(s.button.attrs['aria-label'],'Sembunyikan kata sandi baru');assert.equal(s.button.textContent,'eye-svg');s.button.events.click();assert.equal(s.input.type,'password');assert.equal(s.input.value,'UserSecret123!');assert.equal(s.button.attrs['aria-label'],'Tampilkan kata sandi baru');assert.equal(s.button.textContent,'eye-svg');});
test('valid photo preview replaces and revokes old object urls',()=>{const s=setup();s.select({name:'photo.png',type:'image/png',size:100});assert.equal(s.preview.src,'blob:1');s.select({name:'new.webp',type:'image/webp',size:100});assert.deepEqual(s.revoked,['blob:1']);s.events.pagehide();assert.deepEqual(s.revoked,['blob:1','blob:2']);});
test('unsupported or oversized files cannot replace the saved photo preview',()=>{const s=setup();for(const file of [{name:'photo.svg',type:'image/svg+xml',size:100},{name:'photo.php',type:'image/png',size:100},{name:'photo.png',type:'image/png',size:2097153}]){s.select(file);assert.ok(s.upload.validity);assert.equal(s.preview.src,'/teacher/profile/photo');}assert.equal(s.created.length,0);});
test('canceling the chosen photo restores the stored preview and clears validation',()=>{const s=setup();s.select({name:'photo.png',type:'image/png',size:100});s.select(null);assert.equal(s.preview.src,'/teacher/profile/photo');assert.equal(s.upload.validity,'');assert.equal(s.preview.hidden,false);assert.deepEqual(s.revoked,['blob:1']);});
test('missing or corrupt stored photo falls back to initials',()=>{const s=setup();s.preview.events.error();assert.equal(s.preview.hidden,true);});
