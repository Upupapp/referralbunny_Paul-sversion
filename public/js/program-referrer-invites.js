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
const by=id=>document.getElementById(id),container=by('invite-rows'),template=container.firstElementChild.cloneNode(true),progress=by('invite-progress');let busy=false,group=false;
function add(name='',email=''){if(container.querySelectorAll('.invite-row').length>=50)throw Error('Maximum 50 people per batch.');const r=template.cloneNode(true);r.querySelector('[data-name]').value=name;r.querySelector('[data-email]').value=email;const remove=document.createElement('button');remove.type='button';remove.textContent='Remove';remove.className='text-button';remove.onclick=()=>{if(!busy)r.remove();};r.children[2].replaceWith(remove);container.appendChild(r);}
function groupMode(){group=true;by('invite-tools').style.display='block';by('invite-optional').style.display='none';by('invite-group').hidden=true;by('invite-send').textContent='Send invitations';const first=container.firstElementChild;first.after(by('invite-tools'));}
by('invite-group').onclick=groupMode;
by('invite-add').onclick=()=>{try{add();}catch(e){progress.textContent=e.message;}};
by('invite-import').onclick=()=>by('invite-file').click();
by('invite-file').onchange=async e=>{try{const file=e.target.files[0];if(!file)return;if(file.size>200000)throw Error('CSV is too large.');const records=parseCSV(await file.text());const existing=[...container.querySelectorAll('.invite-row')].filter(r=>r.querySelector('[data-name]').value.trim()||r.querySelector('[data-email]').value.trim());if(existing.length+records.length>50)throw Error('Maximum 50 people per batch.');const emails=new Set(existing.map(r=>r.querySelector('[data-email]').value.trim().toLowerCase()));if(records.some(r=>emails.has(r.email)))throw Error('An imported email is already in this list.');container.querySelectorAll('.invite-row').forEach(r=>{if(!existing.includes(r))r.remove();});records.forEach(r=>add(r.name,r.email));container.querySelector('.invite-row').after(by('invite-tools'));progress.textContent=records.length+' imported. Review the list, then send.';}catch(e){progress.textContent=e.message;}e.target.value='';};
function close(){if(!busy)dialog.close();}by('invite-close').onclick=close;by('invite-cancel').onclick=close;dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});by('invite-done').onclick=()=>location.reload();
by('program-invite-form').onsubmit=async e=>{
 e.preventDefault();if(busy)return;const rows=[...container.querySelectorAll('.invite-row')].filter(r=>r.dataset.sent!=='yes');const seen=new Set();for(const r of rows){const email=r.querySelector('[data-email]').value.trim().toLowerCase();if(seen.has(email)){progress.textContent='Duplicate email: '+email;return;}seen.add(email);}if(!rows.length)return;
 busy=true;dialog.querySelectorAll('button,input').forEach(el=>el.disabled=true);let sent=0,failed=0;
 for(const r of rows){const result=r.querySelector('.result');result.textContent='Sending…';try{const response=await fetch(dialog.dataset.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('#program-invite-form input[name="_token"]').value},body:JSON.stringify({name:r.querySelector('[data-name]').value.trim(),email:r.querySelector('[data-email]').value.trim(),phone:group?null:by('invite-phone').value,territory:group?null:by('invite-territory').value})});const data=await response.json();if(!response.ok)throw Error(data.errors?Object.values(data.errors).flat().join(' '):(data.message||'Could not send.'));r.dataset.sent='yes';result.textContent='Invitation sent';sent++;}catch(err){result.textContent=err.message;failed++;}progress.textContent=sent+' sent · '+failed+' failed. Keep this window open until sending finishes.';if(rows.length>1)await new Promise(resolve=>setTimeout(resolve,1100));}
 busy=false;dialog.querySelectorAll('button,input').forEach(el=>el.disabled=false);container.querySelectorAll('[data-sent="yes"] input').forEach(el=>el.disabled=true);by('invite-done').hidden=false;progress.textContent=sent+' sent · '+failed+' failed. '+(failed?'Fix failed rows and retry; sent rows will be skipped.':'Invitations are ready.');by('invite-send').textContent=failed?'Retry failed invitations':'Send invitations';
};
})();
