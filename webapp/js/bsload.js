/******************************************************************
 * 
 * Base loader
 * 
 ******************************************************************/

'use strict';

// Loading options
const hthead = $('head')
const htinit = [
  "js/const.js",
  "css/index.css",
  "css/login.css",
  "css/titlebar.css",
  "css/sidebar.css",
  "css/content.css",
  "lib/jsonform/underscore.js",
  "lib/jsonform/jsonform.js",
  "js/frm_login.js",
];
const htapp = [
  "js/datatable.js",
  "lib/datatables/1.10.24/jquery.dataTables.js",
  "lib/datatables/1.10.24/jquery.dataTables.css",
  "lib/datatables/plugins/FixFilter.css",
  "lib/datatables/plugins/FixOverflow.css",
  "lib/datatables/plugins/dataTables.scrollResize.js",
  "lib/datatables/plugins/dataTables.rowReorder.min.js",
  "lib/datatables/plugins/ColReorderWithResize.js", 
  "lib/datatables/plugins/rowReorder.dataTables.min.css",
  "lib/simpletabs/simpletabs.js",
  "lib/simpletabs/simpletabs.css",
  "js/frm_titlebar.js",
  "js/frm_sidebar.js",  
  "js/mod_obj.js",
  "js/mod_user.js",
  "js/mod_objconf.js",
  "js/mod_valuemap.js"
];

// Define structural arrays
var mod = {};
var frm = {};

// Function for locking all functions in an array
function lockfuncts(rootobj) {  
  var depth = 0;    // Max depth to prevent infinite loop
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

async function adhead(type, prop) {
  hthead.append($(type, prop));
}

function bsload(htload) {
  let dbg = '';
  // Full reload on deviating build version
  let lockey = `obstack_ver_0x${('0000'+htload.length).slice(-4)}`;
  let strbld = localStorage.getItem(lockey);
  if (strbld != build) {
    dbg = `?_=${$.now()}`;
    localStorage.setItem(lockey,build);
  }
  // Full reload when debug is enabled
  if (typeof debug !== 'undefined' && debug) { dbg = `?_=${$.now()}`; }
  // Load
  $.each(
    htload, 
    function(idx,src) { 
      switch(src.split('.').reverse()[0]) {
        case 'js':       
          //hthead.append($('<script/>', { type: 'text/javascript', src: `${src}${dbg}` }));
          adhead('<script/>', { type: 'text/javascript', src: `${src}${dbg}` });
          break;
        case 'css':
          //hthead.append($('<link/>', { type: 'text/css', rel: 'stylesheet', href: `${src}${dbg}` })); 
          adhead('<link/>', { type: 'text/css', rel: 'stylesheet', href: `${src}${dbg}` });
          break;
      }
    }
  );
  // Freeze functions
  lockfuncts(mod);
  lockfuncts(frm);
}