(() => {
  const list = document.getElementById('workflowSteps');
  const add = document.getElementById('addWorkflowStep');
  const tpl = document.getElementById('workflowStepTemplate');
  const empty = document.getElementById('workflowEmpty');
  if (!list || !add || !tpl) return;
  let drag = null;
  const renumber = () => {
    [...list.querySelectorAll('.workflow-step')].forEach((el, i) => el.querySelector('.step-number').textContent = i + 1);
    empty?.classList.toggle('d-none', list.children.length > 0);
  };
  const wire = (el) => {
    el.querySelector('.remove-workflow-step')?.addEventListener('click', () => { el.remove(); renumber(); });
    el.addEventListener('dragstart', () => { drag = el; el.classList.add('opacity-50'); });
    el.addEventListener('dragend', () => { drag = null; el.classList.remove('opacity-50'); renumber(); });
    el.addEventListener('dragover', e => { e.preventDefault(); if (!drag || drag === el) return; const box = el.getBoundingClientRect(); list.insertBefore(drag, e.clientY < box.top + box.height / 2 ? el : el.nextSibling); });
  };
  [...list.children].forEach(wire);
  add.addEventListener('click', () => { const wrap = document.createElement('div'); wrap.innerHTML = tpl.innerHTML.trim(); const el = wrap.firstElementChild; list.appendChild(el); wire(el); renumber(); });
  renumber();
})();
