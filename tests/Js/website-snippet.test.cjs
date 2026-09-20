const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const source = readFileSync('public/referral-bunny.js', 'utf8');
function load({search='?rb_program=p&rb_ref=member', consent=false, blocked=false, valid=true, storage=new Map()}={}) {
    let calls=0;
    const context = {
        window: {}, document: {currentScript: {dataset: {connection:'c', program:'p', ...(consent ? {consent:'required'} : {})}, src:'https://referralbunny.ai/referral-bunny.js'}},
        location:{search}, URL, URLSearchParams, Date, localStorage:{getItem:k=>{if(blocked) throw Error();return storage.get(k)},setItem:(k,v)=>{if(blocked) throw Error();storage.set(k,v)}},
        fetch:async (url, options)=> { calls++; assert.equal(options.credentials,'omit'); assert.equal(options.referrerPolicy,'no-referrer'); const body=JSON.parse(options.body); return {ok:true,json:async()=>({program_id:'p',attribution_window_days:30,referral:valid && body.membership_id ? {membership_id:body.membership_id} : null})}; }
    };
    vm.runInNewContext(source,context);
    return {api:context.window.ReferralBunny,storage,calls:()=>calls,context};
}
test('captures validated referral with expiry and preserves original arrival on reload',async()=>{
    const a=load(); await a.api.programs.p.ready; const first=a.api.getReferral(); assert.equal(first.membership_id,'member'); assert.ok(first.expires_at>Date.now());
    const b=load({storage:a.storage}); await b.api.programs.p.ready; assert.equal(b.api.getReferral().referred_at,first.referred_at);
});
test('does not save unknown referrals or a different program',async()=>{
    for(const config of [{valid:false},{search:'?rb_program=another&rb_ref=member'}]) { const a=load(config); await a.api.programs.p.ready; assert.equal(a.api.getReferral(),null); }
});
test('works when local storage is unavailable',async()=>{const a=load({blocked:true}); await a.api.programs.p.ready; assert.equal(a.api.getReferral().membership_id,'member');});
test('consent mode makes no requests until granted',async()=>{const a=load({consent:true}); await a.api.programs.p.ready;assert.equal(a.calls(),0);assert.equal(a.api.getReferral(),null);await a.api.programs.p.consent();assert.equal(a.calls(),1);assert.ok(a.api.getReferral());});
test('expired referrals are not returned and invalid links do not overwrite valid ones',async()=>{
    const a=load();await a.api.programs.p.ready;const b=load({valid:false,storage:a.storage});await b.api.programs.p.ready;assert.equal(b.api.getReferral().membership_id,'member');
    const old=JSON.parse(a.storage.get('referralbunny:c'));old.expires_at=Date.now()-1;a.storage.set('referralbunny:c',JSON.stringify(old));assert.equal(b.api.getReferral(),null);
});
test('duplicate script does not double-send installation signal',async()=>{const a=load();await a.api.programs.p.ready;vm.runInNewContext(source,a.context);assert.equal(a.calls(),1);});
