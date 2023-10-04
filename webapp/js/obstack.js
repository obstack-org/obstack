/******************************************************************
 *
 * ObStack App
 *
 ******************************************************************/

// Configuration options
const title = 'ObStack'
const apibase = 'api.php';

// Frame elements
let titlebar;
let sidebar;
let content;
let overlay;

// Structural arrays
let mod = {};
let frm = {};

// ObStack source files
const obauth = [
  "js/frm_login.js"
]
const obinit = [
  "js/const.js",
  "js/frm_titlebar.js",
  "js/frm_sidebar.js",
  "js/mod_obj.js",
  "js/mod_user.js",
  "js/mod_group.js",
  "js/mod_objconf.js",
  "js/mod_valuemap.js"
];

// Content for loader screen
const loader =
  $('<div/>', { class:'content-wrapper', style:'z-index: 20' }).append(
    $('<img/>', { src:'img/sqbf.gif', class:'center', width:40 })
  )

// Document onload function
$(document).ready( function () {
  titlebar = $('<div/>', { class:'titlebar'});
  sidebar  = $('<div/>', { class:'sidebar'});
  content  = $('<div/>', { class:'content'});
  overlay  = $('<div/>', { class:'overlay'});
  $('body').append(
    titlebar,
    sidebar,
    content,
    overlay.append(
      $('<div/>', { class:'overlay-load fadein'}).append(
        $('<img/>', { src:'img/sqbf.gif', class:'center' })
      )
    )
  );

  $.when(
    api('get', 'auth')
  ).fail(function(auth) {
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
      bsload(obinit)
    ).always(function() {
      lockfuncts(mod);
      lockfuncts(frm);
      overlay.remove();
      $.each(def.prop, function(key,value) {
        mod.obj.jftypes[value.id] = value.jftype;
        mod.objconf.options.type[value.id] = value.name;
      });
      $.when(
        api('get','auth/user/self'),
        api('get','objecttype')
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
        frm.titlebar.show();
        frm.sidebar.show(objecttypes);
        if (objecttypes.length >= 1) {
          mod.obj.list(objecttypes[0].id,objecttypes[0].name);
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
      // On debug log responses
      else if (debug) {
        console.log({status:response.status, request:`${httpmethod}: ${path}`, data:data});
        console.log(response.responseText);
      }
    }
  });
  return xhr;
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

// Numeric padding
function tbpad(value, length) {
  return ('0'.repeat(length)+value).slice(-length);
}

// HTML Boolean display shorthand
function htbool(bool) {
  return $('<span/>', { class:`tblc-${bool}` }).text((bool)?'✓':'✗')
}