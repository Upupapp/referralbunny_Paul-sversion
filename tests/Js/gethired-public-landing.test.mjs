import test from 'node:test';
import assert from 'node:assert/strict';
import landing from '../../resources/js/gethired-public-landing.js';
test('selection uses only server-provided estimates and formats earnings without decimals',()=>{const s=landing({defaultPackage:'a',packages:[{id:'a',estimate:{per:69800,annual:83760000}},{id:'b',estimate:{per:29800,annual:35760000}}]});assert.equal(s.calculation.annual,83760000);assert.equal(s.money(s.calculation.annual),'₱837,600');assert.equal(s.money(69800),'₱698');s.selectedPackage='b';assert.equal(s.calculation.annual,35760000);s.selectedPackage='tampered';assert.equal(s.calculation,null);});
test('missing package data never uses a made-up price',()=>{assert.equal(landing({}).calculation,null)});
test('preview and repeated submits cannot call backend',async()=>{const old=global.fetch;global.fetch=()=>{throw Error('Must not request')};await landing({preview:true,open:true}).submit({});let s=landing({open:true});s.busy=true;await s.submit({});global.fetch=old;});
