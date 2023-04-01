/******************************************************************
 * 
 * ObjMan App
 * 
 ******************************************************************/

// Configuration options
const title = 'ObStack'
const apibase = 'api.php';

// Development options
const debug = false;
const build = '230401.1231'

// Frame elements
var titlebar;
var sidebar;
var content;

// Document onload function
$(document).ready( function () {
  titlebar = $('<div/>', { class: 'titlebar'});
  sidebar  = $('<div/>', { class: 'sidebar'});
  content  = $('<div/>', { class: 'content'});
  overlay  = $('<div/>', { class: 'overlay'});
  $('body').append(
    titlebar, 
    sidebar, 
    content,
    overlay.append(
      $('<div/>', { class: 'overlay-load fadein'}).append(
        $('<img/>', { src: 'img/sqbf.gif', class: 'center' })
      )
    )
  );

  bsload(htinit);
  $.when( 
    api('get', 'auth')
  ).fail(function(auth) {    
    // Show login
    overlay.remove();
    frm.login.show();
  }
  ).done(function() {
    // Load content
    bsload(htapp);
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
      frm.titlebar.show();
      frm.sidebar.show(objecttypes[0]);
      if (objecttypes[0].length >= 1) {
        mod.obj.list(objecttypes[0][0].id,objecttypes[0][0].name);
      }
      else {
        content.empty().append(
          $('<div/>', { class: 'content-header' }).html(''),
          $('<div/>', { class: 'content-wrapper' }).html('No object types configured.')
        );
      }
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
  xhr = $.ajax({
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
      else if (true) {
        console.log({status:response.status, request:`${httpmethod}: ${path}`, data:data});
        console.log(response.responseText);
      }
    }
  });
  return xhr;
}

// HTML Boolean display shorthand
function htbool(bool) {
  if (bool) return '&nbsp;✓';
  else return '&nbsp;✗';
}
