(function(){
'use strict';
function parseCSV(text){
 if(text.length>200000)throw Error('CSV is too large. Use at most 50 rows.');
 const rows=[];let row=[],field='',quoted=false;
 text=text.replace(/^\uFEFF/,'');
 for(let i=0;i<text.length;i++){const c=text[i];if(c==='"'){if(quoted&&text[i+1]==='"'){field+='"';i++;}else quoted=!quoted;}else if(c===','&&!quoted){row.push(field);field='';}else if((c==='\n'||c==='\r')&&!quoted){if(c==='\r'&&text[i+1]==='\n')i++;row.push(field);if(row.some(v=>v.trim()))rows.push(row);row=[];field='';}else field+=c;}
 if(quoted)throw Error('CSV has an unclosed quoted field.');row.push(field);if(row.some(v=>v.trim()))rows.push(row);
 const header=(rows.shift()||[]).map(v=>v.trim().toLowerCase());const ni=header.indexOf('name'),ei=header.indexOf('email');if(ni<0||ei<0)throw Error('CSV must have name,email headers.');
 if(!rows.length||rows.length>50)throw Error('Import between 1 and 50 referrers.');
 const seen=new Set();return rows.map((r,i)=>{const name=(r[ni]||'').trim(),email=(r[ei]||'').trim().toLowerCase();if(!name||name.length>150||email.length>254||!/^\S+@\S+\.\S+$/.test(email))throw Error('Check name and email on CSV row '+(i+2)+'.');if(seen.has(email))throw Error('Duplicate email: '+email);seen.add(email);return {name,email};});
}
if(typeof module!=='undefined')module.exports={parseCSV};
if(typeof document==='undefined')return;
const dialog=document.getElementById('program-invite');if(!dialog)return;
const by=id=>document.getElementById(id),container=by('invite-rows'),template=container.firstElementChild.cloneNode(true),progress=by('invite-progress');let busy=false,group=false,hasSent=false;
function add(name='',email=''){if(container.querySelectorAll('.invite-row').length>=50)throw Error('Maximum 50 people per batch.');const r=template.cloneNode(true);r.querySelector('[data-name]').value=name;r.querySelector('[data-email]').value=email;const remove=document.createElement('button');remove.type='button';remove.textContent='Remove';remove.className='text-button';remove.onclick=()=>{if(!busy)r.remove();};r.children[2].replaceWith(remove);container.appendChild(r);}
function groupMode(){group=true;by('invite-tools').style.display='block';by('invite-optional').style.display='none';by('invite-group').hidden=true;by('invite-send').textContent='Send invitations';const first=container.firstElementChild;first.after(by('invite-tools'));}
by('invite-group').onclick=groupMode;
const search=by('invite-search');
function searchRows(){const query=search.value.trim().toLowerCase();container.querySelectorAll('.invite-row').forEach(row=>{const value=row.querySelector('[data-name]').value+' '+row.querySelector('[data-email]').value;row.style.display=value.toLowerCase().includes(query)?'':'none';});}
search.oninput=searchRows;
by('program-invite-form').addEventListener('invalid',()=>{search.value='';searchRows();},true);
by('invite-send').onclick=()=>{search.value='';searchRows();};
by('invite-download').onclick=()=>{
 const failed=[...container.querySelectorAll('.invite-row')].filter(row=>row.dataset.failed==='yes');
 const escape=value=>'"'+(/^[=+@\-\t\r]/.test(value)?"'"+value:value).replace(/"/g,'""')+'"';
 const lines=['name,email,error',...failed.map(row=>[row.querySelector('[data-name]').value,row.querySelector('[data-email]').value,row.querySelector('.result').textContent].map(escape).join(','))];
 const url=URL.createObjectURL(new Blob(['\uFEFF'+lines.join('\r\n')],{type:'text/csv;charset=utf-8'}));
 const link=document.createElement('a');link.href=url;link.download='failed-referrer-invites.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
};

by('invite-add').onclick=()=>{try{add();}catch(e){progress.textContent=e.message;}};
by('invite-import').onclick=()=>by('invite-file').click();
by('invite-file').onchange=async e=>{try{const file=e.target.files[0];if(!file)return;if(file.size>200000)throw Error('CSV is too large.');const records=parseCSV(await file.text());const existing=[...container.querySelectorAll('.invite-row')].filter(r=>r.querySelector('[data-name]').value.trim()||r.querySelector('[data-email]').value.trim());if(existing.length+records.length>50)throw Error('Maximum 50 people per batch.');const emails=new Set(existing.map(r=>r.querySelector('[data-email]').value.trim().toLowerCase()));if(records.some(r=>emails.has(r.email)))throw Error('An imported email is already in this list.');container.querySelectorAll('.invite-row').forEach(r=>{if(!existing.includes(r))r.remove();});records.forEach(r=>add(r.name,r.email));container.querySelector('.invite-row').after(by('invite-tools'));progress.textContent=records.length+' imported. Review the list, then send.';}catch(e){progress.textContent=e.message;}e.target.value='';};
function refreshReferrers(){const url=new URL(location.href);['q','status','delivery','page'].forEach(key=>url.searchParams.delete(key));location.assign(url.href);}
function close(){if(busy)return;if(hasSent)refreshReferrers();else dialog.close();}
by('invite-close').onclick=close;by('invite-cancel').onclick=close;
dialog.addEventListener('cancel',e=>{if(busy||hasSent){e.preventDefault();if(!busy)refreshReferrers();}});
by('invite-done').onclick=refreshReferrers;by('invite-finish').onclick=refreshReferrers;
function complete(){
 by('program-invite-form').hidden=true;by('invite-success').hidden=false;
 by('invite-title').textContent='Invitation complete';
 const sentRows=[...container.querySelectorAll('[data-sent="yes"]')];
 by('invite-success-title').textContent=sentRows.length===1?'Invitation sent':sentRows.length+' invitations sent';
 const receipts=by('invite-receipts');receipts.replaceChildren();
 sentRows.forEach(row=>{const item=document.createElement('li');item.textContent=row.querySelector('[data-name]').value+' · '+row.querySelector('[data-email]').value;item.style.padding='6px 0';receipts.appendChild(item);});
 by('invite-finish').focus();
}

by('program-invite-form').onsubmit=async e=>{
 e.preventDefault();if(busy)return;const rows=[...container.querySelectorAll('.invite-row')].filter(r=>r.dataset.sent!=='yes');const seen=new Set();for(const r of rows){const email=r.querySelector('[data-email]').value.trim().toLowerCase();if(seen.has(email)){progress.textContent='Duplicate email: '+email;return;}seen.add(email);}if(!rows.length)return;
 busy=true;dialog.querySelectorAll('button,input,textarea').forEach(el=>el.disabled=true);let sent=0,failed=0;
 for(const r of rows){const result=r.querySelector('.result');result.textContent='Sending…';try{const response=await fetch(dialog.dataset.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('#program-invite-form input[name="_token"]').value},body:JSON.stringify({message:by('invite-message')?.value||null,name:r.querySelector('[data-name]').value.trim(),email:r.querySelector('[data-email]').value.trim(),phone:group?null:by('invite-phone').value,territory:group?null:by('invite-territory').value})});const data=await response.json();if(!response.ok)throw Error(data.errors?Object.values(data.errors).flat().join(' '):(data.message||'Could not send.'));r.dataset.sent='yes';delete r.dataset.failed;hasSent=true;result.textContent='Invitation sent';sent++;}catch(err){r.dataset.failed='yes';result.textContent=err.message;failed++;}progress.textContent=sent+' sent · '+failed+' failed. Keep this window open until sending finishes.';if(rows.length>1)await new Promise(resolve=>setTimeout(resolve,1100));}
 busy=false;dialog.querySelectorAll('button,input,textarea').forEach(el=>el.disabled=false);
 container.querySelectorAll('[data-sent="yes"]').forEach(row=>row.querySelectorAll('input,button').forEach(el=>el.disabled=true));
 const totalSent=container.querySelectorAll('[data-sent="yes"]').length;
 by('invite-download').hidden=failed===0;
 by('invite-done').hidden=!hasSent;
 progress.textContent=totalSent+' sent · '+failed+' failed. '+(failed?'Correct the failed rows and retry. Already-sent invitations will not be sent again.':'');
 by('invite-send').textContent='Retry failed invitations';
 if(!failed)complete();
 else {by('invite-title').textContent=totalSent?'Some invitations need attention':'Invitations could not be sent';const firstFailed=rows.find(row=>row.dataset.sent!=='yes');firstFailed?.querySelector('[data-email]').focus();}

};
})();
