export default function programMessenger(config) {
 return {
  messages:config.messages || [], body:config.draft || '', sending:false, error:'', network:'', search:'', unreadOnly:false, mobileList:false, newMessages:false, timer:null, pendingKey:null, pendingBody:null, disposed:false, polling:false,
  matches(name,unread){return (!this.unreadOnly || unread>0) && name.toLowerCase().includes(this.search.toLowerCase())},
  visibleConversations(){return (config.conversations||[]).some(c=>this.matches(c.name,c.unread))},
  fit(){const box=this.$root?.querySelector('.msg-workspace');if(box)box.style.setProperty('--msg-height',Math.max(280,(window.visualViewport?.height || window.innerHeight)-box.getBoundingClientRect().top-16)+'px')},
  init(){this.resize=()=>this.fit();window.addEventListener('resize',this.resize);window.visualViewport?.addEventListener('resize',this.resize);this.$nextTick(()=>{this.fit();this.bottom()});if(config.poll)this.timer=setInterval(()=>{if(!document.hidden)this.poll()},10000)},
  destroy(){this.disposed=true;if(this.resize){window.removeEventListener('resize',this.resize);window.visualViewport?.removeEventListener('resize',this.resize)}clearInterval(this.timer);this.controller?.abort()},
  nearBottom(){const e=this.$refs.history;return !e || e.scrollHeight-e.scrollTop-e.clientHeight<100},
  bottom(){this.$nextTick(()=>{const e=this.$refs.history;if(e)e.scrollTop=e.scrollHeight;this.newMessages=false})},
  merge(rows){const ids=new Map(this.messages.map(m=>[m.id,m]));for(const m of rows)ids.set(m.id,m);this.messages=[...ids.values()].sort((a,b)=>a.at.localeCompare(b.at)||a.id.localeCompare(b.id))},
  day(at){return new Date(at).toLocaleDateString('en-US',{timeZone:'UTC',year:'numeric',month:'short',day:'numeric'})},
  time(at){return new Date(at).toLocaleTimeString('en-GB',{timeZone:'UTC',hour:'2-digit',minute:'2-digit'})+' UTC'},
  separator(i){return i===0 || this.day(this.messages[i].at)!==this.day(this.messages[i-1].at)},
  grouped(i){return i>0 && !this.separator(i) && this.messages[i].mine===this.messages[i-1].mine && this.messages[i].sender===this.messages[i-1].sender && new Date(this.messages[i].at)-new Date(this.messages[i-1].at)<300000},
  async poll(){if(this.polling||this.disposed)return;this.polling=true;this.controller=new AbortController();try{const response=await fetch(config.poll,{headers:{Accept:'application/json'},signal:this.controller.signal});if(!response.ok)throw Error('poll');const data=await response.json();if(this.disposed)return;const near=this.nearBottom();const fresh=data.messages.some(m=>!this.messages.some(old=>old.id===m.id));this.merge(data.messages);this.network='';if(fresh){if(near)this.bottom();else this.newMessages=true}}catch(e){if(!this.disposed && e.name!=='AbortError')this.network='Messages may be delayed. Retrying automatically.'}finally{this.polling=false}},
  grow(e){e.style.height='auto';e.style.height=Math.min(128,e.scrollHeight)+'px'},
  async send(){if(this.sending||!this.body.trim()||this.body.length>5000)return;this.sending=true;this.error='';const text=this.body;if(this.pendingBody!==text){this.pendingKey=crypto.randomUUID();this.pendingBody=text}try{const response=await fetch(config.send,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf},body:JSON.stringify({...config.scope,body:text,client_id:this.pendingKey})});if(!response.ok)throw Error(response.status===419?'Your session expired. Reload this page before sending.':response.status===403||response.status===404?'You no longer have access to this conversation.':'Message could not be sent. Your text is kept—try Send again.');const data=await response.json();if(this.disposed)return;this.merge([data.message]);this.body='';this.pendingKey=null;this.pendingBody=null;this.$refs.composer.style.height='auto';this.bottom()}catch(e){if(!this.disposed)this.error=e.message || 'Message could not be sent. Your text is kept.'}finally{this.sending=false}},
  showDetails(){this.$refs.details.showModal();this.$refs.details.querySelector('button').focus()},
  closeDetails(){this.$refs.details.close();this.$refs.detailsTrigger.focus()},
 };
}
