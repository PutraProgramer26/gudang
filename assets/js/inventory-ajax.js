(function () {
  if (window.inventoryAjaxBound) {
    return;
  }
  window.inventoryAjaxBound = true;

  var appSelector = '#inventory-app';

  function showMessage(message, type, container) {
    var feedback = container ? container.querySelector('.modal-body') : document.querySelector('#inventory-feedback');
    if (!feedback) {
      return;
    }
    if (container) {
      var previousMessage = feedback.querySelector('.inventory-action-error');
      if (previousMessage) {
        previousMessage.remove();
      }
    } else {
      feedback.innerHTML = '';
    }
    var alert = document.createElement('div');
    alert.className = 'alert alert-' + type + ' alert-dismissible fade show';
    if (container) {
      alert.classList.add('inventory-action-error');
    }
    alert.setAttribute('role', 'status');
    alert.appendChild(document.createTextNode(message));
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'close';
    close.setAttribute('data-dismiss', 'alert');
    close.setAttribute('aria-label', 'Tutup');
    close.innerHTML = '&times;';
    alert.appendChild(close);
    if (container) {
      feedback.insertBefore(alert, feedback.firstChild);
    } else {
      feedback.appendChild(alert);
    }
  }

  function closeOpenModal() {
    var modal = document.querySelector('#inventory-app .modal.show');
    if (!modal || !window.jQuery) {
      return Promise.resolve();
    }
    return new Promise(function (resolve) {
      var completed = false;
      var finish = function () {
        if (!completed) {
          completed = true;
          resolve();
        }
      };
      modal.addEventListener('hidden.bs.modal', finish, { once: true });
      window.jQuery(modal).modal('hide');
      window.setTimeout(finish, 500);
    });
  }

  async function replaceInventory(url, options) {
    options = options || {};
    var response = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    var markup = await response.text();
    var documentFromResponse = new DOMParser().parseFromString(markup, 'text/html');
    var nextApp = documentFromResponse.querySelector(appSelector);
    if (!response.ok || !nextApp) {
      throw new Error('Data inventory gagal dimuat. Periksa koneksi lalu coba lagi.');
    }

    await closeOpenModal();
    var currentApp = document.querySelector(appSelector);
    if (!currentApp) {
      return;
    }
    currentApp.replaceWith(nextApp);
    window.history.replaceState({}, '', url.toString());

    if (options.message) {
      showMessage(options.message, 'success');
    }
    if (options.itemId) {
      var row = document.querySelector('[data-inventory-id="' + Number(options.itemId) + '"]');
      if (row) {
        row.classList.add('table-success');
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    } else if (options.focus === 'history') {
      var history = document.querySelector('#inventory-history');
      if (history) {
        history.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    } else if (options.focus === 'form') {
      var itemForm = document.querySelector('#inventory-app form[action*="modules/inventory/proses.php"]');
      if (itemForm) {
        itemForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  }

  function getFormUrl(form) {
    var url = new URL(form.action || window.location.href, window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    return url;
  }

  function getMutationUrl(data, action) {
    var url = new URL(window.location.href);
    url.searchParams.set('module', 'inventory');
    url.searchParams.delete('pesan');
    url.searchParams.delete('detail');
    url.searchParams.delete('edit');
    url.searchParams.delete('riwayat');

    if (action === 'hapus_gudang') {
      url.searchParams.delete('gudang_id');
      url.searchParams.delete('kategori');
      url.searchParams.delete('kondisi');
      url.searchParams.delete('cari');
    } else if (data.gudang_id) {
      url.searchParams.set('gudang_id', data.gudang_id);
    }

    if (data.nama_item) {
      url.searchParams.set('cari', data.nama_item);
      url.searchParams.delete('kategori');
      url.searchParams.delete('kondisi');
    }
    return url;
  }

  document.addEventListener('submit', async function (event) {
    var form = event.target.closest(appSelector + ' form');
    if (!form) {
      return;
    }
    event.preventDefault();
    var submitButton = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      if ((form.method || 'get').toLowerCase() === 'get') {
        await replaceInventory(getFormUrl(form));
        return;
      }

      var response = await fetch(form.action, {
        method: (form.method || 'post').toUpperCase(),
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      var data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Aksi tidak dapat diproses.');
      }

      var action = form.querySelector('[name="aksi"]');
      var nextUrl = getMutationUrl(data, action ? action.value : '');
      await replaceInventory(nextUrl, {
        message: data.message,
        itemId: data.item_id || null,
        focus: data.item_id ? null : 'list'
      });
    } catch (error) {
      showMessage(error.message || 'Terjadi kesalahan saat memproses data.', 'danger', form.closest('.modal'));
    } finally {
      if (submitButton && submitButton.isConnected) {
        submitButton.disabled = false;
      }
    }
  });

  document.addEventListener('click', async function (event) {
    var link = event.target.closest(appSelector + ' a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('data-toggle')) {
      return;
    }
    var targetUrl = new URL(link.href, window.location.href);
    var isProcessAction = targetUrl.pathname.indexOf('/modules/inventory/proses.php') !== -1;
    var isInventoryNavigation = targetUrl.searchParams.get('module') === 'inventory';
    if (!isProcessAction && !isInventoryNavigation) {
      return;
    }

    event.preventDefault();
    if (link.dataset.confirm && !window.confirm(link.dataset.confirm)) {
      return;
    }

    try {
      if (isProcessAction) {
        var response = await fetch(targetUrl, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        var data = await response.json();
        if (!response.ok || !data.ok) {
          throw new Error(data.message || 'Aksi tidak dapat diproses.');
        }
        var action = targetUrl.searchParams.get('aksi');
        var nextUrl = getMutationUrl(data, action);
        await replaceInventory(nextUrl, {
          message: data.message,
          itemId: action === 'hapus_item' ? null : data.item_id || null
        });
      } else {
        var focus = targetUrl.searchParams.has('riwayat') ? 'history' : targetUrl.searchParams.has('edit') ? 'form' : null;
        await replaceInventory(targetUrl, { focus: focus });
      }
    } catch (error) {
      showMessage(error.message || 'Terjadi kesalahan saat memproses data.', 'danger', link.closest('.modal'));
    }
  });
})();