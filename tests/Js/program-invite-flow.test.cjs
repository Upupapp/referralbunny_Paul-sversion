const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/js/program-referrer-invites.js', 'utf8');
function setup(count, responses) {
 const element = () => ({style:{},dataset:{},value:'',hidden:false,disabled:false,textContent:'',focus(){this.focused=true},replaceChildren(){this.items=[]},appendChild(item){(this.items??=[]).push(item)}});
 const ids = Object.fromEntries(['program-invite','invite-rows','invite-progress','invite-tools','invite-optional','invite-group','invite-send','invite-add','invite-import','invite-file','invite-close','invite-cancel','invite-done','invite-finish','program-invite-form','invite-success','invite-title','invite-success-title','invite-receipts','invite-phone','invite-territory'].map(id=>[id,element()]));
 const rows = Array.from({length:count},(_,i)=>{const r=element(),name=element(),email=element(),result=element();name.value='Test '+i;email.value=`test${i}@example.com`;r.querySelector=s=>s==='[data-name]'?name:s==='[data-email]'?email:result;r.querySelectorAll=()=>[name,email];r.cloneNode=()=>r;return r;});
 ids['invite-rows'].firstElementChild=rows[0];
 ids['invite-rows'].querySelectorAll=s=>s.includes('data-sent')?rows.filter(r=>r.dataset.sent==='yes'):rows;
 ids['program-invite'].dataset.endpoint='/invite';ids['program-invite'].querySelectorAll=()=>Object.values(ids);ids['program-invite'].addEventListener=(name,fn)=>ids.cancelHandler=fn;
 const calls=[];const location={href:'https://example.com/referrers?program_id=program&q=old&status=active&page=3',assign(url){this.assigned=url}};
 vm.runInNewContext(source,{URL,document:{getElementById:id=>ids[id],createElement:element,querySelector:()=>({value:'csrf'})},location,fetch:async(url,options)=>{calls.push(JSON.parse(options.body));const ok=responses.shift();return {ok,json:async()=>ok?{}:{message:'Please try again'}}},setTimeout:fn=>fn()});
 return {ids,rows,calls,location,submit:()=>ids['program-invite-form'].onsubmit({preventDefault(){}})};
}
test('success replaces editable form and close refreshes unfiltered program list',async()=>{
 const s=setup(1,[true]);await s.submit();assert.equal(s.ids['program-invite-form'].hidden,true);assert.equal(s.ids['invite-success'].hidden,false);assert.equal(s.ids['invite-success-title'].textContent,'Invitation sent');assert.equal(s.ids['invite-receipts'].items.length,1);assert.equal(s.ids['invite-finish'].focused,true);s.ids['invite-close'].onclick();assert.equal(s.location.assigned,'https://example.com/referrers?program_id=program');
});
test('partial retry sends only failed recipients and success counts all sends',async()=>{
 const s=setup(2,[true,false,true]);await s.submit();assert.equal(s.ids['program-invite-form'].hidden,false);assert.equal(s.ids['invite-send'].textContent,'Retry failed invitations');assert.equal(s.rows[0].querySelector('[data-email]').disabled,true);await s.submit();assert.equal(s.calls.length,3);assert.equal(s.calls[2].email,'test1@example.com');assert.equal(s.ids['invite-success-title'].textContent,'2 invitations sent');assert.equal(s.ids['program-invite-form'].hidden,true);
});
