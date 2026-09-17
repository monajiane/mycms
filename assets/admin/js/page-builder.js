/* assets/admin/js/page-builder.js — Page Builder engine */
(function () {
  const TEMPLATES = {
    hero: { title: 'سربرگ', icon: '🌟', fields: [
      { k: 'eyebrow', t: 'text', l: 'متن کوچک بالا' },
      { k: 'title', t: 'text', l: 'عنوان اصلی' },
      { k: 'subtitle', t: 'textarea', l: 'زیرعنوان' },
      { k: 'image', t: 'text', l: 'تصویر (URL یا /uploads/...)' },
      { k: 'cta_label', t: 'text', l: 'متن دکمه' },
      { k: 'cta_url', t: 'text', l: 'لینک دکمه' },
    ]},
    features: { title: 'ویژگی‌ها', icon: '✨', fields: [
      { k: 'title', t: 'text', l: 'عنوان بخش' },
      { k: 'items', t: 'textarea', l: 'آیتم‌ها (هر خط: عنوان|توضیح|ایموجی)' },
    ]},
    products: { title: 'محصولات', icon: '🛍', fields: [
      { k: 'title', t: 'text', l: 'عنوان' },
      { k: 'category_id', t: 'text', l: 'شناسهٔ دسته (خالی = همه)' },
      { k: 'limit', t: 'text', l: 'تعداد' },
    ]},
    text: { title: 'متن آزاد', icon: '📝', fields: [
      { k: 'content', t: 'textarea', l: 'محتوا (HTML مجاز است)' },
    ]},
    image: { title: 'تصویر', icon: '🖼', fields: [
      { k: 'src', t: 'text', l: 'آدرس تصویر' },
      { k: 'alt', t: 'text', l: 'متن جایگزین' },
      { k: 'caption', t: 'text', l: 'زیرنویس' },
    ]},
    cta: { title: 'دعوت به اقدام', icon: '📣', fields: [
      { k: 'title', t: 'text', l: 'عنوان' },
      { k: 'subtitle', t: 'textarea', l: 'توضیح' },
      { k: 'button_label', t: 'text', l: 'متن دکمه' },
      { k: 'button_url', t: 'text', l: 'لینک' },
      { k: 'color', t: 'text', l: 'رنگ پس‌زمینه (مثل #f59e0b)' },
    ]},
    form: { title: 'فرم تماس', icon: '📬', fields: [
      { k: 'title', t: 'text', l: 'عنوان' },
      { k: 'fields', t: 'textarea', l: 'فیلدها (هر خط: نام|placeholder|اجباری)' },
      { k: 'submit_label', t: 'text', l: 'متن دکمه' },
    ]},
    testimonials: { title: 'نظرات مشتریان', icon: '💬', fields: [
      { k: 'title', t: 'text', l: 'عنوان' },
      { k: 'items', t: 'textarea', l: 'نظرات (هر خط: نام|نظر|ستاره 1-5)' },
    ]},
    faq: { title: 'پرسش و پاسخ', icon: '❓', fields: [
      { k: 'title', t: 'text', l: 'عنوان' },
      { k: 'items', t: 'textarea', l: 'پرسش و پاسخ (سطر خالی جداکننده، خط اول: سوال، خط بعد: جواب)' },
    ]},
    video: { title: 'ویدیو', icon: '🎬', fields: [
      { k: 'url', t: 'text', l: 'آدرس ویدیو (YouTube/Vimeo)' },
      { k: 'caption', t: 'text', l: 'توضیح' },
    ]},
    pricing: { title: 'تعرفه', icon: '💎', fields: [
      { k: 'title', t: 'text', l: 'عنوان' },
      { k: 'plans', t: 'textarea', l: 'پلن‌ها (هر خط: نام|قیمت|ویژگی‌ها با ,|پیشنهادی‌بله/خیر)' },
    ]},
  };

  const canvas = document.getElementById('pbCanvas');
  const json = document.getElementById('blocksJson');
  if (!canvas) return;
  let blocks = JSON.parse(json.value || '[]');

  function render() {
    if (!blocks.length) {
      canvas.innerHTML = '<div class="pb-empty-canvas">⬅ از پالت پایین، یک بلوک اضافه کنید.</div>';
      return;
    }
    canvas.innerHTML = blocks.map((b, i) => blockHTML(b, i)).join('');
    bindBlockEvents();
  }

  function blockHTML(b, i) {
    const tpl = TEMPLATES[b.type] || { title: b.type, icon: '❓', fields: [] };
    const fields = tpl.fields.map(f => {
      const v = b[f.k] || '';
      if (f.t === 'textarea') return `<label>${f.l}<textarea data-k="${f.k}" data-i="${i}" rows="3">${escapeHtml(v)}</textarea></label>`;
      return `<label>${f.l}<input type="text" data-k="${f.k}" data-i="${i}" value="${escapeHtml(v)}"></label>`;
    }).join('');
    return `<div class="pb-block" data-i="${i}">
      <div class="pb-block__head">
        <span class="pb-block__title">${tpl.icon} ${tpl.title}</span>
        <div class="pb-block__actions">
          <button type="button" data-act="up" title="بالا">↑</button>
          <button type="button" data-act="down" title="پایین">↓</button>
          <button type="button" data-act="dup" title="کپی">⎘</button>
          <button type="button" data-act="del" title="حذف">×</button>
        </div>
      </div>
      <div class="pb-block__body">${fields}</div>
    </div>`;
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function bindBlockEvents() {
    canvas.querySelectorAll('input, textarea').forEach(el => {
      el.addEventListener('input', e => {
        const i = +e.target.dataset.i;
        const k = e.target.dataset.k;
        blocks[i][k] = e.target.value;
        sync();
      });
    });
    canvas.querySelectorAll('[data-act]').forEach(btn => {
      btn.addEventListener('click', e => {
        const block = e.target.closest('.pb-block');
        const i = +block.dataset.i;
        const act = e.target.dataset.act;
        if (act === 'del') blocks.splice(i, 1);
        else if (act === 'up' && i > 0) [blocks[i-1], blocks[i]] = [blocks[i], blocks[i-1]];
        else if (act === 'down' && i < blocks.length-1) [blocks[i+1], blocks[i]] = [blocks[i], blocks[i+1]];
        else if (act === 'dup') blocks.splice(i+1, 0, { ...blocks[i] });
        render(); sync();
      });
    });
  }

  function sync() { json.value = JSON.stringify(blocks); }

  document.querySelectorAll('[data-add]').forEach(btn => {
    btn.addEventListener('click', () => {
      const type = btn.dataset.add;
      const def = {};
      TEMPLATES[type].fields.forEach(f => def[f.k] = f.t === 'textarea' ? '' : '');
      blocks.push({ type, ...def });
      render(); sync();
      canvas.lastElementChild?.scrollIntoView({ behavior: 'smooth' });
    });
  });

  document.querySelectorAll('.pb-tab').forEach(t => {
    t.addEventListener('click', () => {
      document.querySelectorAll('.pb-tab').forEach(x => x.classList.toggle('is-active', x === t));
      const p = t.dataset.pane;
      document.querySelectorAll('.pb-pane').forEach(x => x.hidden = x.dataset.pane !== p);
    });
  });

  render();
})();
