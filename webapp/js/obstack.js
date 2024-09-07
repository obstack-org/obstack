/******************************************************************
 *
 * ObStack App
 *
 ******************************************************************/

// Configuration options
let title = 'ObStack';
const apibase = 'api.php';

// Frame elements
let titlebar;
let sidebar;
let content;
let overlay;

// Structural arrays
let mod = {};
let frm = {};
let cfg = [];

// ObStack source files
const obauth = [
  "js/frm_login.js",
  "lib/qrcode/jquery.qrcode.min.js"
]
const obinit = [
  "js/const.js",
  "js/frm_titlebar.js",
  "js/frm_sidebar.js",
  "js/mod_obj.js",
  "js/mod_user.js",
  "js/mod_group.js",
  "js/mod_conf.js",
  "js/mod_objconf.js",
  "js/mod_valuemap.js",
  "lib/crypto-js/crypto-js.js"
];

// Content for loader screen
const loader =
  $('<div/>', { class:'content-loader', style:'z-index: 20' }).append(
    $('<img/>', { src:'img/sqbf.gif', class:'center', width:60 })
  )

// Change observer
let change = {
  state: false,
  check: function(okfnc){
    if (change.state) {
      obAlert('You have unsaved changes, do you want to continue?', { Ok:function(){ change.reset(); okfnc(); }, Cancel:null });
    }
    else {
      okfnc();
    }
  },
  change: function(){
    change.state = true;
  },
  reset: function(){
    change.observer.disconnect();
    change.state = false;
  },
  observer: new MutationObserver(function() { change.state = true; }),
  observe: function(){
    change.observer.disconnect();
    $.each(content.find('.obTable-tb'), function() {
      change.observer.observe(this, { subtree:true, childList:true, characterData:true, attributes:true, attributeFilter:['class'] });
    });
    $('.obTabs-tab-content').find('form').find('input, select, textarea').on('change', function(){ change.state = true; });
    $('.obTabs-tab-content').find('table').find('input').on('change', function(){ change.state = true; });
  }
}

// State
let state = {
  set: function(mod, params=null){
    let state = { mod:mod, ts:$.now() };
    if (params != null) {
      state.params = params;
    }
    localStorage.setItem('obstack:state', JSON.stringify(state));
  },
  reset: function(mod, params=null){
    state.set(mod, params);
    location.reload(true);
  },
  restore: function(){
    let state = JSON.parse(localStorage.getItem('obstack:state'));
    if (state != null && ($.now() - state.ts) < 600000) {
      if (!state.params) {
        mod[state.mod].list();
      }
      else {
        mod[state.mod].list(state.params);
      }
      return true;
    }
    else {
      return false;
    }
  },
  forget: function(){
    localStorage.removeItem('obstack:state');
  }
}

// Document onload function
let basebq = null;
$(document).ready( function () {
  $.when(
    api('get','config')
  ).done(function(cfgdata) {
    cfg=cfgdata;
    setConfig(cfg.settings);
    titlebar = $('<div/>', { class:'titlebar'});
    sidebar  = $('<div/>', { class:'sidebar'});
    content  = $('<div/>', { class:'content'});
    overlay  = $('<div/>', { class:'overlay'});
    $('body').append(
      titlebar,
      sidebar,
      content
    );

    $.when(
      api('get', 'auth')
    ).fail(function() {
      // Show login
      $.when(
        bsload(obauth)
      ).always(function() {
        overlay.remove();
        frm.login.show();
      });
    }).done(function() {
      // Load content
      $.when(
        bsload(obinit, true)
      ).always(function() {
        lockfuncts(mod);
        lockfuncts(frm);
        overlay.remove();
        $.when(
          api('get','auth/user/self'),
          api('get','objecttype?display=map')
        ).done(function(self, objecttypes) {
          mod.user.self = self[0];
          objecttypes = JSON.parse(JSON.stringify(objecttypes[0]));
          objecttypes.sort(function (a, b) {
            let data = [a,b];
            for(let i = 0; i <= 1; i++) {
              data[i] = data[i]['name'].trim();
              if (data[i].match(/^\d*(\s|$)/)) {
                data[i] = tbpad(data[i].slice(0, data[i].search(/[a-zA-Z\-\s_]/)),12) + data[i].slice(data[i].search(/[a-zA-Z\-\s_]/));
              }
            }
            return data[0].localeCompare(data[1]);
          });
          mod.config.navigation.maps = cfg.navigation;
          frm.titlebar.show();
          frm.sidebar.show(objecttypes, cfg.navigation);
          if (objecttypes.length >= 1) {
            if (!state.restore()) {
              mod.obj.list(objecttypes[0].id,objecttypes[0].name);
            }
          }
          else {
            content.empty().append(
              $('<div/>', { class:'content-header' }).html(''),
              $('<div/>', { class:'content-wrapper' }).html('No object types available.')
            );
          }
        });
      });
    });
    basebq = getCookie('obstack_basebq');
  })
  .fail(function() {
    obAlert(response.error, null);
  });
});

$(window).bind('beforeunload',function(){
  if (change.state) {
    return 'You have unsaved changes, do you want to continue?';
  }
});

// global default onclick
$(document).on('click', function(event) {
  if (event.target.className != 'titlebar-control-img') {
    if (event.target.className == 'titlebar-dropdown-item') {
      $('#titlebar-dropdown').hide();
    }
    else {
      $('#titlebar-dropdown').slideUp('fast');
    }
  }
  if (event.target.className != 'obForm-info') {
    $('.obForm-info-popup').each(function() {
      $(this).remove();
    });
  }
});

// API function
function api(httpmethod, path, data) {
  let xhr = $.ajax({
    url: `${apibase}/v2/${path}`,
    type: httpmethod,
    dataType: 'json',
    contentType: 'application/json; charset=utf-8',
    data: JSON.stringify(data),
    error: function (response) {
      // On error 401 (unauthorized) reload to login screen
      if (response.status == 401) {
        if (path != 'auth') {
          location.reload(true);
        }
      }
      else if (response.status == 404) {
        obAlert('No access to object');
      }
      else if (response.status == 428) {
        obAlert(response.responseJSON.error, []);
      }
      else {
        obAlert(response.responseJSON.error);
      }
      // On debug log responses
      if (debug) {
        console.log({status:response.status, request:`${httpmethod}: ${path}`, data:data});
        console.log(response.responseText);
      }
      // Header Logs
      log(xhr);
    },
    success: function (response, status, xhr) {
      try {
        if ('error' in response) {
          obAlert(response.error, null);
        }
      }
      catch(err) {}
      // Header Logs
      log(xhr);
    }
  });
  return xhr;
}

// Log from xhr
function log(xhr) {
  let log = xhr.getResponseHeader('obstack-log');
  if (log != null) {
    console.log(log);
  }
}

// Function for locking all functions in an array
function lockfuncts(rootobj) {
  let depth = 0;    // Max depth to prevent infinite loop
  $.each(rootobj, function (key, value) {
    if ((typeof value === 'object') && (value.tagName !== 'DIV') && (depth < 16 )) {
      lockfuncts(value);
      depth++;
    }
    if (typeof value === 'function') {
      Object.freeze(value);
    }
  });
}

// Get object from list by value
function getObject(list, key, value) {
  for (let i in list) {
    if (key in list[i]) {
      if (list[i][key] == value) return list[i];
    }
  }
  return null;
}

// Get cookie
function getCookie(name) {
  let re = new RegExp(name + "=([^;]+)");
  let value = re.exec(document.cookie);
  return (value != null) ? decodeURIComponent(value[1]) : null;
}

// Sort list of objects
function lsSort(list, key) {
  list.sort(function (a, b) {
    let data = [a,b];
    for(let i = 0; i <= 1; i++) {
      data[i] = data[i][key].trim();
      if (data[i].match(/^\d*(\s|$)/)) {
        data[i] = tbpad(data[i].slice(0, data[i].search(/[a-zA-Z\-\s_]/)),12) + data[i].slice(data[i].search(/[a-zA-Z\-\s_]/));
      }
    }
    return data[0].localeCompare(data[1]);
  });
  return list;
}

// Set layout etc
function setConfig(data) {
  let settings = {};
  $.each(data, function(id, value) {
    settings[value.name] = value.value;
    if (value.name.indexOf('css_') == 0) {
      $('body').get(0).style.setProperty('--'+value.name.substr(4), value.value+((value.name.substr(4)=='sidebar-width')?'px':''));
    }
    if (value.name == 'title' && $.trim(value.value).length > 0) {
      title = 'ObStack - ' + value.value;
    }
    if (value.name == 'totp_default_enabled') {
      cfg.settings.totp_default_enabled = (value.value == '1');
    }
  });
}

// Numeric padding
function tbpad(value, length) {
  return ('0'.repeat(length)+value).slice(-length);
}

// HTML Boolean display shorthand
function htbool(bool) {
  return $('<span/>', { class:`tblc-${bool}` }).text((bool)?'✓':'✗')
}