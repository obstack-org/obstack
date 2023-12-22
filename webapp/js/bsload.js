/******************************************************************
 *
 * Base loader
 *
 ******************************************************************/

'use strict';

// Development options
const debug = false;
const build = '231222'

// Loading options
const hthead = $('head');
const htinit = [
  "css/index.css",
  "css/login.css",
  "css/titlebar.css",
  "css/sidebar.css",
  "css/content.css",
  "lib/obui/obui.js",
  "lib/obui/obui.css",
  "js/obstack.js",
];

async function bsload(srclist, reset) {
  let dbg = bsdbg(reset);
  $.each(srclist, function(ids, src) {
    setTimeout(function() {
      switch(src.split('.').reverse()[0]) {
        case 'js':
          hthead.append($('<script/>', { type: 'text/javascript', src: `${src}${dbg}` }));
          break;
        case 'css':
          hthead.append($('<link/>', { type: 'text/css', rel: 'stylesheet', href: `${src}${dbg}` }));
          break;
      }
    },0);
    return true;
  });
}

function bsdbg(reset) {
  let dbg = '';
  let strbld = localStorage.getItem('obstack:build');
  if ((strbld != build) || debug) {
    dbg = `?_=${$.now()}`;
    if (typeof reset == 'boolean' && reset) {
      localStorage.setItem('obstack:build', build);
    }
  }
  return dbg;
}

bsload(htinit);