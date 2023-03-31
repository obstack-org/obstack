/******************************************************************
 * 
 * datatable
 *  .html()		Function
 *  .table()		Function
 *  .addrow()		Function
 *  .update()		Function
 *  .options()		Function
 *  .getoptions()	Function
 *  .create()		Function
 *  .apidata()		Function
 *  .apicolumns()	Function
 * 
 * Example:
 *  var testtable = new datatable();
 *  $('#MyParentDiv').append(testtable.html());
 *  testtable.apidata(api('get','path/to/data'));
 *  testtable.options({
 *    columns: [{title:'[id]'}, {title:'Object Type'}],
 *    columnDefs: [...dtobjtypes.getoptions().columnDefs, { targets:[1], orderable:false }]
 *  });
 *  testtable.create();
 * 
 ******************************************************************/

var datatable = function() {
  var httable = null;           // DataTable object
  var htelem = $('<table/>', { class: 'stripe cell-border pointer' });
  var dtchunksize = 800;        // Chunk size for phased loading of data
  var dtoptions = {             // DataTable options defaults
    dom: 'Rlfrtip',
    scrollY: 100,     
    paging: false, 
    scrollResize: true,
    scrollCollapse: true,
    colReorder: { 'allowReorder': false },    
    columnDefs: [ 
      { targets: '_all', className: 'dt-head-left datatables-fixoverflow' },
      { targets: [0], searchable: false },
      { targets: [0], visible: false },
    ],
    columns: [{title:' '}],
    data: []
  }
  // Return HTML element
  this.html = function(){
    return htelem;
  }
  // Return DataTable object
  this.table = function() {
    return httable;
  }
  // Add row
  this.addrow = function(data) {
    httable.row.add(data).draw(false);
  }
  // Update row
  this.update = function(rowid, data) {
    httable.row(rowid).data(data).draw(false);
  }
  // Add to DataTable options
  this.options = function(options) {
    $.extend(dtoptions, options);
  }
  // Get DataTable options
  this.getoptions = function() {
    return dtoptions;
  }
  // Generate DataTable
  this.create = function() {
    // Load data in chunks for a faster loading experience
    if (dtoptions.data.length > dtchunksize) {
      let tmpdata = dtoptions.data;
      dtoptions.data = tmpdata.slice(0, dtchunksize);
      httable = htelem.DataTable(dtoptions);
      let i = 1;
      interval = setInterval(function() {
        let chunk;
        if (tmpdata.length <= (i*dtchunksize)) {
          clearInterval(interval);
          return;
        }
        else {
          chunk = tmpdata.slice(i*dtchunksize, (i+1)*dtchunksize);
          httable.rows.add(chunk).draw();
          i++;
        }        
      }, 0);
    }
    // Load all data at once
    else {
      httable = htelem.DataTable(dtoptions);
    }
  }
  // Convert and preserve data from API
  this.apidata = function(jsdata) {
    dtoptions.columns = [];
    dtoptions.data = [];
    $.each(jsdata[0], function(key, value) {      
      dtoptions.columns = [...dtoptions.columns, {'title': key}];
    });
    $.each(jsdata, function(index, object) {      
      dtoptions.data[index] = [];
      $.each(object, function(key, value) {
        dtoptions.data[index] = [...dtoptions.data[index], value];
      });      
    });
  }
  // Convert and preserve columns from API
  this.apicolumns = function(jsdata) {
    dtoptions.columns = [{title:'[id]'}];
    col_id   = 1;
    colhide  = [0];
    colorder = [0];
    $.each(jsdata, function(key, value) {      
      dtoptions.columns = [...dtoptions.columns, {title:value.name}];
      if (!value.tbl_visible)   { colhide  = [...colhide,  col_id]; }
      if (!value.tbl_orderable) { colorder = [...colorder, col_id]; }
      col_id++;
    });
    dtoptions.columnDefs = [
      dtoptions.columnDefs[0], 
      dtoptions.columnDefs[1],
      { targets: colhide, visible: false },
      { targets: colorder, orderable: false },
    ];
  }
};