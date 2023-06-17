/*******************************************************************
 *
 *  obUI v1.2 (2023-04-08)
 * ========================
 *
 * Requires: jQuery, jQueryUI
 * Functions: obTabs, obTable, obForm
 *
 * Part of ObStack (https://www.obstack.org). License: GPL-3.0
 *
 * ****************************************************************/


/*******************************************************************
 *  obTabs(tabs)
 * ==============
 *   tabs         : Tab structure (JSON)
 *
 * Example:
 * --------
 *  var obTabs = $('<div/>');
 *  $('#ParentDiv').append(obTabs);
 *  obTabs.obTabs({
 *    tabs: [
 *      { title: 'Tab 1', html: $('<div/>').html('Tab 1 Content') },
 *      { title: 'Tab 2', html: $('<div/>').html('Tab 2 Content') }
 *    ]
 *  });
 ******************************************************************/

(function($) {
  "use strict";

  // obTabs initialization
  $.fn.obTabs = function ( options ) {
    var pid = $(this)[0].id;
    var out = $('<div/>', { id: pid+'_obTabs' });
    var tabs = $('<div/>', { id: pid+'_obTabs-tabs', 'class': 'obTabs-tabs' });
    var cont = $('<div/>', { id: pid+'_obTabs-content', 'class': 'obTabs-content' });
    $.each(options.tabs, function (key, value) {
      tabs.append(
        $('<div/>', { id: pid+'_obTab'+key, 'class': 'obTabs-tab' })
          .html(value.title)
          .bind('click', function(event) {
            obTabClick('#'+$(this)[0].id);
          })
        );
      cont.append(
        $('<div/>', { id: pid+'_obTab'+key+'-content', 'class': 'obTabs-tab-content' })
          .html(value.html)
      );
    });
    out.append(tabs);
    out.append(cont);
    this.html(out);
    obTabClick('#'+pid+'_obTab0');
  }

  // obTabs onclick
  function obTabClick(tabname) {
    $('.obTabs-tab').removeClass('active');
    $('.obTabs-tab-content').hide();
    $(tabname).addClass('active');
    let tabcontent = $(tabname+'-content');
    tabcontent.show();
    $.each(tabcontent.find('.obTable-tb'), function() {
      $(this).obTableRedraw();
    });
  }

})(jQuery);


/*******************************************************************
 *  obTable(options)
 * ==================
 * Options:
 *   id                 : Table id (optional, but required for custom user column width)
 *   element            : HTML element to update/transform (optional)
 *   data               : Table data, supports both list and JSON (uses key as id)
 *   columns            : Column names (JSON, overwrites columns derived from data)
 *   columns_resizable  : Enable resizing on all columns
 *   columns_orderable  : Default for ordering on all columns
 *   columns_hidden     : Array of column keys that must be hidden
 *   sortable           : Enable dragging/reordering rows
 *
 *  $(element).obTableSort(colnr)
 * ===============================
 *  Sort table by column number
 *
 *  $(element).obTableRedraw()
 * ===============================
 *  Redraw table and column sizes
 *
 * Example:
 * --------
 *   var table = new obTable({
 *     id: 'demotable',
 *     data: [
 *       { id: 1, Name:'Row 1', Value: 'Value 1' },
 *       { id: 2, Name:'Row 2', Value: 'Value 2' },
 *       { id: 3, Name:'Row 3', Value: 'Value 3' }
 *     ];
 *     columns: [
 *       { id: 'demotable-column-1', name:'Name', orderable: true },
 *       { id: 'demotable-column-2', name:'Value', orderable: false }
 *     ],
 *     columns_resizable: true,
 *     columns_hidden: ['id'],
 *     sortable: true
 *   });
 *   $('body').append(table.html());
 *   table.draw();
 *   table.html().on('click', 'td', function () {
 *     if (!$(this).hasClass('obTable-drag')) {
 *       console.log(JSON.parse($(this).parent().attr('hdt')).id);
 *     }
 *   }
 *   table.html().obTableRedraw();
 *   $('#some_element').append(
 *     $('<input/>', { width: 300, class: 'search' })
 *       .on('keyup', function() { table.search(this.value); })
 *   );
 *
 ******************************************************************/

$(window).on('resize', function(){
  jQuery('.obTable-tb').each(function() {
    $(this).height($(this).parents('table').parent().height()-40);
  });
});

(function($) {
  "use strict";

  $.fn.obTableSort = function(colnr) {
    if (!$(this).hasClass('obTable-tb')) {
      return false;
    }
    let tb = $(this).parents('table').find('tbody');
    let tr = Array.prototype.slice.call(tb[0].rows, 0);
    tr = tr.sort(function (a, b) {
      let data = [a,b];
      for(let i = 0; i <= 1; i++) {
        data[i] = data[i].cells[colnr].textContent.trim();
        if (data[i].match(/^\d*(\s|$)/)) {
          data[i] = tbpad(data[i].slice(0, data[i].search(/[a-zA-Z\-\s_]/)),12) + data[i].slice(data[i].search(/[a-zA-Z\-\s_]/));
        }
      }
      return data[0].localeCompare(data[1]);
    });
    for(let i = 0; i < tr.length; ++i) tb.append(tr[i]);

    function tbpad(value, length) {
      return ('0'.repeat(length)+value).slice(-length);
    }
  }

  $.fn.obTableRedraw = function() {
    if (!$(this).hasClass('obTable-tb')) {
      return false;
    }
    let obtable = $(this).parents('table');
    let obparent = obtable.parent();
    let obcolumns = 0;
    let storage = null;
    let defwidth = null;
    if (typeof obtable.attr('obttid') != 'undefined') {
      try {
        storage = JSON.parse(localStorage.getItem(`obTable:${obtable.attr('obttid')}`));
      } catch (e) {}
    }

    $(this).height(obparent.height()-40);

    $.each(obtable.find('th'), function(idx, th) {
      if (!$(th).hasClass('obTable-thdrag')) {
        obcolumns++;
      }
    });
    defwidth = (obparent.width()-135)/obcolumns;
    if (storage == null) {
      $.each(obparent.find('th, td'), function(idx, td) {
        td = $(td);
        td.css('flex-basis', defwidth);
      });
    }
    else {
      defwidth = defwidth/obcolumns/2;
      $.each(obparent.find('th'), function(idx, th) {
        th = $(th);
        if (!th.hasClass('obTable-thdrag')) {
          let obtcol = th.attr('obtcol');
          let colwidth = defwidth;
          try {
            colwidth = storage.columns[th.attr('obtcid')].width;
          } catch(e) {}
          th.css('flex-basis', colwidth);
          $.each(obtable.children('tbody').find('tr'), function(idx, tr) {
            $(tr.cells[obtcol]).css('flex-basis', colwidth);
          });
        }
      });
    }
  }

})(jQuery);

var obTable = function(coptions) {

  /******************************************************************
   * Constructor
   ******************************************************************/

  // Options
  var options = {
    id: null,
    element: $('<table/>'),
    data: null,
    columns: null,
    columns_resizable: false,
    columns_orderable: false,
    columns_hidden : [],
    sortable: false
  }
  $.extend(options, coptions);

  // Base element
  if (typeof options.element == 'undefined') {
    options.element = $('<table/>');
  }
  else if (options.element[0].tagName != 'TABLE') {
    console.error('obTable element is not a table');
    return false;
  }
  options.element.addClass('obTable').attr('obttid', options.id);

  // Columns
  if (options.columns == null) {
    let columns = [];
    options.columns = [];
    // JSON
    if ((typeof options.data[0]) == 'undefined') {
      columns = options.data[Object.keys(options.data)[0]];
    }
    // Array
    else {
      if (options.data.length > 0) {
        columns = options.data[0];
      }
    }
    //Process
    $.each(columns, function(key, value) {
      if ($.inArray(key, options.columns_hidden) == -1) {
        options.columns = [
          ...options.columns,
          {
            id: key,
            name: key,
            orderable: options.columns_orderable
          }];
        }
    });
  }

  // Table Head
  let i = 0;
  let thead = $('<thead/>');
  let trhead = $('<tr/>');
  if (options.sortable) {
    i++;
    trhead.append($('<th/>', {class:'obTable-thdrag'}).append(''));
  }
  $.each(options.columns, function(id, column) {
    if ($.inArray(id, options.columns_hidden) == -1) {
      let th = $('<th/>');
      th.attr('obtcid', column.id)
        .attr('obtcol', i)
        .append(column.name);
      if (column.orderable) {
        th.attr('obtsrt', 0)
          .addClass('obTable-sort')
          .click(function() { tbsort($(this)); })
          .append('&nbsp;&nbsp', $('<img/>', { src:'img/crn.png', width:10 }));
      }
      if (options.columns_resizable) {
        th.resizable({
          start: function(e, ui) {
            obtcol = ui.originalElement.attr('obtcol');
            minwidth = parseInt(ui.originalElement.css('min-width'));
            fullwidth = ui.originalElement.width() + ui.originalElement.next().width();
            minoffset = ui.originalElement.offset().left - $(ui.originalElement).parent().offset().left;
            resizebar = ui.originalElement.parents('table').find('.obTable-resize');
            resizebar.show();
          },
          resize: function(e, ui) {
            nextwidth = fullwidth - ui.size.width;
            if ((fullwidth - ui.size.width) > minwidth) {
              ui.originalElement.css('flex-basis', ui.size.width);
              ui.originalElement.next().css('flex-basis', nextwidth );
              resizebar.css('left', minoffset+ui.originalElement.width()+22);
            }
          },
          stop: function(e, ui) {
            resizebar.hide();
            if (options.id != null) {
              nextwidth = fullwidth - ui.size.width;
              $.each(ui.originalElement.parents('table').children('tbody').find('tr'), function(idx, tr) {
                $(tr.cells[obtcol]).css('flex-basis', ui.size.width);
                $(tr.cells[obtcol]).next().css('flex-basis', nextwidth);
              });
              let storage = { columns: {} };
              $.each(ui.originalElement.parent().find('th'), function(idx, th) {
                th = $(th);
                if (!th.hasClass('obTable-thdrag')) {
                  if (typeof storage.columns[th.attr('obtcid')] == 'undefined') {
                    storage.columns[th.attr('obtcid')] = {}
                  }
                  storage.columns[th.attr('obtcid')].width = parseInt(th.css('flex-basis'));
                }
              });
              localStorage.setItem(`obTable:${options.id}`, JSON.stringify(storage));
            }
          }
        });
      }
      else {
        localStorage.removeItem(`obTable:${options.id}`);
      }
      i++;
      trhead.append(th);
    }
  });

  $.each(trhead.find('.ui-resizable-e'), function(idx, div) {
    $(div).html('⁞').click(function() { return false; });
  });
  thead.append(trhead);

  // Table Body
  let tbody = $('<tbody/>', {class:'obTable-tb'});
  if (options.sortable) {
    tbody = $('<tbody/>', {class:'obTable-tb'}).sortable({
      axis: 'y',
      handle: '.obTable-drag',
      placeholder: 'obTable-plc',
      helper: function (e, ui) {
        ui.children().each(function() {
          $(this).width($(this).width());
        });
        return ui;
      },
      sort: function (e, ui) {
        scrolltop = parseInt($(this).scrollTop());
        $(ui.helper)
          .css('margin-top', scrolltop)
          .css('margin-bottom', scrolltop);
        return ui;
      },
      stop: function (e, ui) {
        $(this).children('tr').each(function() {
          $(this)
            .css('margin-top', 0)
            .css('margin-bottom', 0);
        });
      }
    });
  }

  // Sort data
  if ((!options.sortable) && (options.data.length > 0)) {
    $.each(Object.keys(options.data[0]).reverse(), function(idx, key) {
      if ($.inArray(key, options.columns_hidden) == -1) {
        options.data = options.data.sort(function(a, b) {
          let data = [a[key],b[key]];
          for(let i = 0; i <= 1; i++) {
            if (data[i] != null) {
              if (typeof data[i] == 'boolean') {
                data[i] = (data[i]) ? '0' : '1';
              }
              if (typeof data[i] != 'object') {
                if (data[i].match(/^\d*(\s|$)/)) {
                  data[i] = tbpad(data[i].slice(0, data[i].search(/[a-zA-Z\-\s_]/)),12) + data[i].slice(data[i].search(/[a-zA-Z\-\s_]/))
                }
              }
            }
          }
          if ((typeof data[0] != 'object') && (typeof data[1] != 'object'))  {
            if (data[0]) return data[1] ? data[0].localeCompare(data[1]) : -1;
          }
        });
      };
    });
  }

  // Load data (phased)
  let dtchunksize = 800;
  if (options.data.length > dtchunksize) {
    let i = 0;
    interval = setInterval(function() {
      if (i*dtchunksize < options.data.length) {
        let chunk = options.data.slice(i*dtchunksize, (i+1)*dtchunksize);
        $.each(chunk, function (rowkey, rowdata) {
          tbrow(tbody, rowkey, rowdata);
        });
        if (i==0) {
          $.each(options.element.find('.obTable-tb'), function() { $(this).obTableRedraw(); });
        }
        i++;
      }
      else {
        $.each(options.element.find('.obTable-tb'), function() { $(this).obTableRedraw(); });
        clearInterval(interval);
        return;
      }
    }, 0);
  }
  // Load data (all data at once)
  else {
    $.each(options.data, function (rowkey, rowdata) {
      tbrow(tbody, rowkey, rowdata);
    });
    setTimeout(function() {
      $.each(options.element.find('.obTable-tb'), function() { $(this).obTableRedraw(); });
    }, 0);
  }

  // Build table
  options.element.append(
    $('<div/>', {class:'obTable-thsrc'}),
    $('<div/>', {class:'obTable-resize'}),
    thead,
    tbody
  );


  /******************************************************************
   * Public
   ******************************************************************/

  // Retreive table element
  this.html = function() {
    return options.element;
  }

  // Search / Filter data
  this.search = function(query) {
    query = query.toLowerCase();
    $.each(options.element.find('tbody').find('tr'), function(idx,tr) {
      tr = $(tr);
      let found = false;
      $.each(tr.find('td'), function(idx, td) {
        if ($(td).html().toLowerCase().indexOf(query) >= 0) {
          found = true;
        }
      });
      if (found) { tr.show(); }
      else { tr.hide(); }
    });
  }

  /******************************************************************
   * Private
   ******************************************************************/

  // Table row
  function tbrow(tbody, rowkey, rowdata) {
    let row = $('<tr/>');
    let hdt = {};
    if (options.sortable) {
      row.append($('<td/>', { class: 'obTable-drag' }).append('⇅'));
    }
    $.each(rowdata, function (key,value) {
      if ($.inArray(key, options.columns_hidden) != -1) {
        hdt[key] = value;
      }
      else {
        row.append($('<td/>').append(value));
      }
    });
    row.attr('id', rowkey).attr('hdt', JSON.stringify(hdt));
    tbody.append(row);
  }

  // Table sorting
  function tbsort(col) {
    // Attributes and images
    let img = col.find('img');
    $.each(col.parents('thead').find('th'), function(idx, th) {
      th = $(th);
      if (th.attr('obtcol') != col.attr('obtcol')) {
        th.attr('obtsrt', 0);
        th.find('img').attr('src','img/crn.png');
      }
    });
    // Sorting direction
    if (col.attr('obtsrt') == -1) {
      col.attr('obtsrt', 1);
      img.attr('src','img/cru.png');
    }
    else {
      col.attr('obtsrt', -1);
      img.attr('src','img/crd.png');
    }
    // Sort
    let tb = col.parents('table').find('tbody');
    let tr = Array.prototype.slice.call(tb[0].rows, 0);
    tr = tr.sort(function (a, b) {
      let data = [a,b];
      for(let i = 0; i <= 1; i++) {
        data[i] = data[i].cells[col.attr('obtcol')].textContent.trim();
        if (data[i].match(/^\d*(\s|$)/)) {
          data[i] = tbpad(data[i].slice(0, data[i].search(/[a-zA-Z\-\s_]/)),12) + data[i].slice(data[i].search(/[a-zA-Z\-\s_]/));
        }
      }
      return -((+col.attr('obtsrt')) || -1) * (data[0].localeCompare(data[1]));
    });
    for(let i = 0; i < tr.length; ++i) tb.append(tr[i]);
  }

  // Numeric padding
  function tbpad(value, length) {
    return ('0'.repeat(length)+value).slice(-length);
  }

}
