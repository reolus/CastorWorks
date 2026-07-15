document.addEventListener('DOMContentLoaded',()=>{
 const body=document.body,sidebar=document.getElementById('portalSidebar'),toggle=document.getElementById('sidebarToggle'),collapse=document.getElementById('sidebarCollapse');
 const groups=[...document.querySelectorAll('.sidebar-group')];
 const collapsed=()=>body.classList.contains('sidebar-collapsed')&&window.innerWidth>=992;
 if(localStorage.getItem('rbes.sidebar.collapsed')==='1'&&window.innerWidth>=992)body.classList.add('sidebar-collapsed');
 if(toggle&&sidebar){toggle.addEventListener('click',()=>sidebar.classList.toggle('show'));document.addEventListener('click',e=>{if(window.innerWidth<992&&!sidebar.contains(e.target)&&!toggle.contains(e.target))sidebar.classList.remove('show')});}
 if(collapse){collapse.addEventListener('click',()=>{body.classList.toggle('sidebar-collapsed');localStorage.setItem('rbes.sidebar.collapsed',body.classList.contains('sidebar-collapsed')?'1':'0');groups.forEach(g=>g.classList.remove('flyout-open'));});}
 groups.forEach(group=>{
   const button=group.querySelector('.sidebar-group-toggle');if(!button)return;
   const key='rbes.sidebar.group.'+group.dataset.sidebarGroup;
   const stored=localStorage.getItem(key);
   const active=Boolean(group.querySelector('a.active'));
   const initial=stored===null?active:stored==='1';
   group.classList.toggle('is-open',initial);button.setAttribute('aria-expanded',initial?'true':'false');
   button.addEventListener('click',event=>{
     if(collapsed())return;
     event.preventDefault();
     const opening=!group.classList.contains('is-open');
     group.classList.toggle('is-open',opening);
     button.setAttribute('aria-expanded',opening?'true':'false');
     localStorage.setItem(key,opening?'1':'0');
   });
 });
});

/* Phase 17: stable collapsed-sidebar flyouts */
(() => {
  const body = document.body;
  const groups = [...document.querySelectorAll('.sidebar-group')];
  let closeTimer = null;

  const collapsed = () => body.classList.contains('sidebar-collapsed') && window.innerWidth >= 992;

  const closeAll = (except = null) => {
    groups.forEach((group) => {
      if (group !== except) group.classList.remove('flyout-open');
    });
  };

  const positionFlyout = (group) => {
    const button = group.querySelector('.sidebar-group-toggle');
    const menu = group.querySelector('.sidebar-submenu');
    if (!button || !menu) return;
    const rect = button.getBoundingClientRect();
    const viewportPadding = 12;
    const estimatedHeight = Math.min(menu.scrollHeight || 420, window.innerHeight * .7);
    const top = Math.max(viewportPadding, Math.min(rect.top, window.innerHeight - estimatedHeight - viewportPadding));
    menu.style.setProperty('--sidebar-flyout-top', `${top}px`);
    menu.style.top = `${top}px`;
  };

  groups.forEach((group) => {
    const button = group.querySelector('.sidebar-group-toggle');
    const menu = group.querySelector('.sidebar-submenu');
    if (!button || !menu) return;

    group.addEventListener('mouseenter', () => {
      if (!collapsed()) return;
      clearTimeout(closeTimer);
      closeAll(group);
      positionFlyout(group);
      group.classList.add('flyout-open');
    });

    group.addEventListener('mouseleave', () => {
      if (!collapsed()) return;
      clearTimeout(closeTimer);
      closeTimer = window.setTimeout(() => group.classList.remove('flyout-open'), 350);
    });

    menu.addEventListener('mouseenter', () => clearTimeout(closeTimer));
    menu.addEventListener('mouseleave', () => {
      if (!collapsed()) return;
      closeTimer = window.setTimeout(() => group.classList.remove('flyout-open'), 250);
    });

    button.addEventListener('click', (event) => {
      if (!collapsed()) return;
      event.preventDefault();
      event.stopPropagation();
      const opening = !group.classList.contains('flyout-open');
      closeAll(group);
      if (opening) {
        positionFlyout(group);
        group.classList.add('flyout-open');
      } else {
        group.classList.remove('flyout-open');
      }
    });
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.portal-sidebar')) closeAll();
  });

  window.addEventListener('resize', () => {
    if (!collapsed()) closeAll();
  });
})();


/* Phase 19: command search, themes, and user preferences */
(()=>{
 const modal=document.getElementById('searchCommand'),trigger=document.getElementById('globalSearchTrigger'),input=document.getElementById('globalSearchInput'),results=document.getElementById('globalSearchResults'),close=document.getElementById('globalSearchClose');let timer;
 const open=()=>{if(!modal)return;modal.hidden=false;document.body.classList.add('search-open');setTimeout(()=>input?.focus(),20)};const shut=()=>{if(!modal)return;modal.hidden=true;document.body.classList.remove('search-open')};
 trigger?.addEventListener('click',open);close?.addEventListener('click',shut);modal?.querySelector('.search-command-backdrop')?.addEventListener('click',shut);document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();open()}if(e.key==='Escape')shut()});
 input?.addEventListener('input',()=>{clearTimeout(timer);const q=input.value.trim();if(q.length<2){results.innerHTML='<div class="search-empty">Type at least two characters.</div>';return}results.innerHTML='<div class="search-empty"><i class="fa-solid fa-spinner fa-spin"></i> Searching...</div>';timer=setTimeout(async()=>{try{const r=await fetch('/portal/search?q='+encodeURIComponent(q),{headers:{Accept:'application/json'}}),d=await r.json();if(!d.results?.length){results.innerHTML='<div class="search-empty">No matching records.</div>';return}results.innerHTML=d.results.map(g=>`<section><h3><i class="fa-solid ${g.icon}"></i>${esc(g.label)}</h3>${g.items.map(i=>`<a href="${i.url}"><strong>${esc(i.title)}</strong><span>${esc(i.subtitle||'')}</span></a>`).join('')}</section>`).join('')}catch(e){results.innerHTML='<div class="search-empty text-danger">Search failed.</div>'}},220)});
 const esc=v=>{const d=document.createElement('div');d.textContent=v??'';return d.innerHTML};
 const themeToggle=document.getElementById('themeToggle');themeToggle?.addEventListener('click',()=>{const html=document.documentElement,next=html.dataset.theme==='dark'?'light':'dark';html.dataset.theme=next;document.cookie='rbes_theme='+next+';path=/;max-age=31536000;SameSite=Lax';themeToggle.querySelector('i').className=next==='dark'?'fa-regular fa-sun':'fa-regular fa-moon'});
 document.querySelectorAll('[data-save-preference]').forEach(el=>el.addEventListener('change',()=>fetch('/portal/preferences/save',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams({csrf_token:window.RBES.csrf,key:el.dataset.savePreference,value:el.value})})));
})();

/* Phase 19 dashboard widget visibility */
(()=>{document.querySelectorAll('.dashboard-widget-toggle').forEach(toggle=>{const key='rbes_widget_'+toggle.dataset.widgetTarget;const saved=localStorage.getItem(key);if(saved!==null)toggle.checked=saved==='1';const apply=()=>{document.querySelectorAll(`[data-widget="${toggle.dataset.widgetTarget}"]`).forEach(w=>w.hidden=!toggle.checked)};apply();toggle.addEventListener('change',()=>{localStorage.setItem(key,toggle.checked?'1':'0');apply()})})})();
