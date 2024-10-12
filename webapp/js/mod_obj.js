/******************************************************************
 *
 * mod
 *  .obj
 *    .jftypes          Array
 *    .list()           Function
 *    .open()           Function
 *    .open_htgen()     Function
 *    .save()           Function
 *    .relations        Array
 *      .open()         Function
 *
 ******************************************************************/

mod['obj'] = {

  /******************************************************************
   * mod.obj.jftypes
   * ====================
   * Array of field types used in jsonForm schema. Content is loaded
   * from const after loading module
   ******************************************************************/
  jftypes: {},

  /******************************************************************
   * mod.obj.list(type)
   * ===================
   * List objects of type
   *    type    : Object type UUID
   ******************************************************************/
  list: function(type) {

    // Loader
    state.set('obj', [type]);
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Load and display data
    $.when(
      api('get',`objecttype/${type}?format=aggr`),
      api('get',`objecttype/${type}/object?format=text&display=table`)
    ).done(function(apidata_type, apidata_objects) {
      apidata_type = apidata_type[0];
      apidata_objects = apidata_objects[0];

      let columns = [];
      let columns_remove = [];
      let columns_allowhtml = [];
      $.each(apidata_type.property, function(id, column) {
        if (column.tbl_visible) {
          columns = [...columns, { id:column.id, name:column.name, orderable:column.tbl_orderable}];
          if (column.type == 1) {
            $.each(apidata_objects, function(idx, rec) {
              if (apidata_objects[idx][column.id] != null && apidata_objects[idx][column.id].trim().match(/^(ht|f)tp/) && apidata_objects[idx][column.id].trim().match(/(ht|f)tp[s]?\:\/\/[a-zA-Z0-9\-\.\:@]+([a-zA-Z0-9\/\:\.\-_?=&#%]*)?$/)) {
                columns_allowhtml = [...columns_allowhtml, column.id];
                apidata_objects[idx][column.id] = $('<a/>', { href:apidata_objects[idx][column.id], target:'_blank'}).text(apidata_objects[idx][column.id]).on('click').on('click', function(event) {
                  event.stopPropagation();
                });
              }
            });
          }
          // Checkbox: V or X
          if (column.type == 5) {
            columns_allowhtml = [...columns_allowhtml, column.id];
            $.each(apidata_objects, function(idx, rec) {
              apidata_objects[idx][column.id] = htbool(apidata_objects[idx][column.id]);
            });
          }
          // Password
          if (column.type == 12) {
            columns_allowhtml = [...columns_allowhtml, column.id];
            $.each(apidata_objects, function(idx, rec) {
              apidata_objects[idx][column.id] = [
                ...apidata_objects[idx][column.id],
                '&emsp;', $('<img/>', { class:'pointer', src:'img/iccpy.png', style:'margin-bottom:-3px;', width:14, title:'Copy' }).on('click', function(event) {
                  event.stopPropagation();
                  let img = $(this);
                  mod.obj.properties.open_pwd(apidata_type.objecttype.id, JSON.parse(img.parents('tr').attr('hdt')).id, column.id, img);
                })
              ];
            });
          }
          // File
          if (column.type == 15) {
            columns_allowhtml = [...columns_allowhtml, column.id];
            $.each(apidata_objects, function(idx, rec) {
              let fname = apidata_objects[idx][column.id];
              if (fname != null && fname.length > 0) {
                let id = this.id;
                let fext = fname.substr( (fname.lastIndexOf('.') +1) ).toLowerCase();
                let fcontent = `${apibase}/v2/objecttype/${apidata_type.objecttype.id}/object/${id}/property/${column.id}/content?fn=${fname}`;
                if ($.inArray(fext, def.mediatype) != -1) {
                  apidata_objects[idx][column.id] = [
                    $('<img/>', { class:'pointer', src:'img/icmgn.png', style:'margin-bottom:-3px;', width:12, title:'Preview' }).on('click', function(event) {
                      event.stopPropagation();
                      if (event.ctrlKey || event.shiftKey) {
                        window.open(`${fcontent}&format=view`, '_blank');
                      }
                      else {
                        mod.obj.properties.open_img(apidata_type.objecttype.id, id, column, '.content-wrapper', fname);
                      }
                    })
                    , '&ensp;', ...fname
                  ];
                }
                else if ($.inArray(fext, def.doctype) != -1) {
                  apidata_objects[idx][column.id] = [
                    $('<img/>', { class:'pointer', src:'img/icbrw.png', style:'margin-bottom:-3px;', width:12, title:'View in new tab' }).on('click', function(event) {
                        event.stopPropagation();
                        window.open(`${fcontent}&format=view`, '_blank');
                      })
                      , '&ensp;', ...fname
                    ];
                }
                else {
                  apidata_objects[idx][column.id] = [
                    $('<img/>', { class:'pointer', src:'img/icdwn.png', style:'margin-bottom:-3px;', width:12, title:'Download' }).on('click', function(event) {
                        event.stopPropagation();
                        window.location.href = content;
                      })
                      , '&ensp;', ...fname
                    ];

                }
              }
            });
          }

        }
        else {
          columns_remove = [...columns_remove, column.name];
        }
      });
      for (let i=0; i<apidata_objects.length; i++) {
        $.each(columns_remove, function(idx, name) {
          delete apidata_objects[i][name];
        });
      }

      let objlist = new obFTable({
        table: {
          id: type,
          data: apidata_objects,
          columns: columns,
          columns_resizable: true,
          columns_hidden: ['id'],
          columns_allowhtml: columns_allowhtml
        },
        search:   true,
        create:   (apidata_type.acl.create)?function() { mod.obj.open(type, null); } : null,
        open:     function(td) {
          mod.obj.open(type, JSON.parse($(td).parent().attr('hdt')).id);
        },
        footer:   'Objects'
      });
      let objlist_html = objlist.html();

      content.empty().append(new obContent({
        name: $('<span/>', { text:apidata_type.objecttype.name }),
        content: objlist_html
      }).html());

      $.each(content.find('.obTable-tb'), function() { $(this).obTableRedraw(); });
      loader.remove();
    });

  },

  /******************************************************************
   * mod.obj.open(type, id)
   * ===================
   * Open object of type with id
   *    type    : Object type UUID
   *    id      : Object UUID
   ******************************************************************/
  open: function(type, id) {

    // Loader
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // New
    if (id == null) {
      $.when(
        api('get',`objecttype/${type}?format=aggr&display=edit`)
      ).done(function(apidata) {
        mod.obj.open_htgen(type, id, apidata);
      });
    }
    // Open
    else {
      $.when(
        api('get',`objecttype/${type}/object/${id}?format=aggr&display=edit`)
      ).done(function(apidata) {
        mod.obj.open_htgen(type, id, apidata);
      });
    }
  },

  /******************************************************************
   * mod.obj.open_htgen()
   * ====================
   * Generate page from API data
   *    type    : Object type UUID
   *    id      : Object UUID
   *    api_..  : API data
   ******************************************************************/
  open_htgen: function(type, id, apidata) {
    let api_objtype = apidata.objecttype;
    api_objtype.acl = apidata.acl;

    let api_obj = [];
    let api_log = [];
    let api_obj_short = [{ name:apidata.name }];
    let api_relations = [];

    if (id != null) {
      api_relations = apidata.relation;
      api_obj = apidata.object;
      if (apidata.objecttype.log) {
        api_log = apidata.log;
      }
    }

    // Load
    let acl_save = (mod.user.self.sa)?true:(id==null)?api_objtype.acl.create:api_objtype.acl.update;
    let propform_data = [];
    let pwrecv = [];
    $.each(apidata.property, function(key, property) {
      if (property.frm_visible) {
        propform_data = [
          ...propform_data, {
            id:property.id,
            name:property.name,
            type:def.property_type[property.type],
            value:(property.id in api_obj)?api_obj[property.id]:null,
            readonly:(!acl_save || property.frm_readonly)
          }];
        if (property.type == 12) {
          pwrecv = [...pwrecv, property.id];
        }
      }
     });
     let propform = new obForm(propform_data);
     let propform_html = propform.html();

    // Options for selects
    $.each(apidata.property, function(key, property) {
      if ([3, 4].includes(property.type)) {
        let select = propform_html.find(`#${property.id}`);
        let value = (property.id in api_obj)?api_obj[property.id]:null;
        select.empty();
        // Object Type
        if (property.type == 3) {
          let otdata = apidata.meta.objecttype[property.type_objtype];
          $.each(lsSort(otdata, 'name'), function(idx, option) {
            let htopt = $('<option/>').text(option.name).val(option.id);
            if (option.id == value) {
              htopt.attr('selected','selected');
            }
            select.append(htopt);
          });
        }
        // Value Map
        if (property.type == 4) {
          let vmdata = apidata.meta.valuemap[property.type_valuemap];
          $.each(vmdata, function(idx, option) {
            let htopt = $('<option/>').text(option.name).val(option.id);
            if (option.id == value) {
              htopt.attr('selected','selected');
            }
            select.append(htopt);
          });
        }
      }
    });

    // Options for fields: File, Password
    $.each(apidata.property, function(key, property) {
      if ([15].includes(property.type)) {
        let ficon = [];
        let felem = propform_html.find(`#${property.id}`);
        let fname = felem.val();
        let fext = fname.substr( (fname.lastIndexOf('.') +1) ).toLowerCase();
        let fcontent = `${apibase}/v2/objecttype/${type}/object/${id}/property/${property.id}/content?fn=${fname}`;

        propform_html.find(`#${property.id}`)
          .css('display','inline-block')
          .after(
            '&emsp;', $('<img/>', { class:'pointer', src:'img/icmap.png', style:'margin-bottom:-5px;', width:18, title:'Generate' }).on('click', function() {
              propform_html.find(`#${property.id}`).click();
            })
          );

        felem.on('click', function(){
          setTimeout(function() {
            let finput = $('#f_'+property.id);
            let fpopup = $(`#obp_${property.id}`);
            let lcontrol = fpopup.find('.obPopup-lcontrol');

            if (finput.length == 0 && fname.length > 0) {
              // Media file preview
              if ($.inArray(fext, def.mediatype) != -1) {
                fpopup.css('height', '400px');
                fpopup.find('span').replaceWith(
                  $('<img/>', {
                    src: `${apibase}/v2/objecttype/${type}/object/${id}/property/${property.id}/content?fn=${fname}&format=view`,
                    style: 'max-width:600px; max-height:350px; position:absolute; top:50%; left:50%; transform:translate(-50%,-58%);',
                    class: 'pointer'
                  }).on('click', function(){
                    window.open(`${fcontent}&format=view`, '_blank');
                    $('.obPopup-overlay').parent().remove();
                  })
                );
              }
              // Download icon
              lcontrol.append(
                '&emsp;',
                $('<img/>', { class:'pointer', src:'img/icdwn.png', width:16, title:'Download' }).on('click', function() {
                  window.location.href = fcontent;
                })
              );
              // New-tab icon
              if ($.inArray(fext, [...def.mediatype, ...def.doctype]) != -1) {
                lcontrol.append(
                  '&emsp;',
                  $('<img/>', { class:'pointer', src:'img/icbrw.png', width:16, title:'View in new tab' }).on('click', function() {
                    window.open(`${fcontent}&format=view`, '_blank');
                  })
                );
              }
            }
          }, 10);
        });

        felem
          .css('display','inline-block')
          .before('<br>')
          .after(ficon);
      }
      if ([12].includes(property.type)) {
        propform_html.find(`#${property.id}`)
          .css('display','inline-block')
          .before('<br>')
          .after(
            '&emsp;', $('<img/>', { class:'pointer', src:'img/icpsg.png', style:'margin-bottom:-3px;', width:16, title:'Generate' }).on('click', function() {
              obPwgen($('#_obTab0-content'), $(this).siblings(':input'));
            }),
            '&emsp;', $('<img/>', { class:'pointer', src:'img/icmgn.png', style:'margin-bottom:-3px;', width:16, title:'Open' }).on('click', function() {
              mod.obj.properties.open_pwd(type, id, property.id);
            }),
            '&ensp;', $('<img/>', { class:'pointer', src:'img/iccpy.png', style:'margin-bottom:-3px;', width:16, title:'Copy' }).on('click', function() {
              mod.obj.properties.open_pwd(type, id, property.id, $(this));
            })
          );
      }
    });

    // Relations
    $.each(api_relations, function(idx, rec) {
      let colnr = 0;
      $.each(rec, function(key, value) {
        if (colnr > 6) {
          delete api_relations[idx][key];
        }
        colnr++;
      });
      for (let i=colnr; i<=6; i++) {
        api_relations[idx][`dmy${i}`] = null;
      }
    });

    let rellist = null;
    if (api_relations != null) {
      rellist = new obFTable({
        table: {
          id: `${type}_relations`,
          data: api_relations,
          columns: [
            { id: 'rtp', name:'Type', orderable: true },
            { id: 'rf1', name:'Field', orderable: true },
            { id: 'rf2', name:'', orderable: false },
            { id: 'rf3', name:'', orderable: false },
            { id: 'rf4', name:'', orderable: false }
          ],
          columns_resizable: true,
          columns_hidden: ['id', 'objtype']
        },
        search:   true,
        create:   (!acl_save)?null:function() { mod.obj.relations.open(type, id, rellist.table(), null); },
        open:     function(td) {
          let tr = $(td).parent();
          if (tr.hasClass('delete')) {
            obAlert('Do you want to remove the deletion mark?', { Ok:function(){ tr.removeClass('delete'); }, Cancel:null });
          }
          else {
            mod.obj.relations.open(type, id, rellist.table(), tr, acl_save);
          }
        }
      });
    }

    // Logs
    let loglist = null;
    if (mod.user.self.sa && api_objtype.log && (id != null)) {
      loglist =$('<table/>', { class: 'log-table' });
      loglist.append(
        $('<th/>', { class: 'log-th', width: 160}).text('Date/Time'),
        $('<th/>', { class: 'log-th', width: 100 }).text('User'),
        $('<th/>', { class: 'log-th', width: 100 }).text('Action'),
        $('<th/>', { class: 'log-th', width: 450 }).text('Details')
      );
      $.each(api_log, function(idx, log) {
        loglist.append(
          $('<tr/>').append(
            $('<td/>', { class: 'log-td' }).text(log.timestamp),
            $('<td/>', { class: 'log-td' }).text(log.username),
            $('<td/>', { class: 'log-td' }).text(def.logtype[parseInt(log.action)]),
            $('<td/>', { class: 'log-td' }).text(log.details)
          )
        )
      });
    }

    // Draw
    let obtabs = new obTabs({ tabs: [
      { title:'Properties', html:$('<div/>', { class:'content-tab-wrapper' }).append(propform_html) },
      (rellist==null)?null:{ title:'Relations',  html:$('<div/>', { class:'content-tab-wrapper' }).append(rellist.html()) },
      (loglist==null)?null:{ title:'Log', html:$('<div/>', { class:'content-tab-wrapper' }).append(loglist) }
    ]});

    let obcontent = {
      name: [$('<a/>', { class:'link', text:`${api_objtype.name}`, click:function() { change.check(function() { mod.obj.list(type); }); } }), $('<span/>', { text:` / ${(id==null)?'[new]':api_obj_short[0].name}`})],
      content: obtabs.html(),
      control: [
        // -- Save
        (!acl_save)?null:$('<input/>', { class:'btn', type:'submit', value:'Save'  }).on('click', function() {
          let propform_data = propform.validate();
          $.each(apidata.property, function(key, property) {
            if ([12,15].includes(property.type)) { propform_html.find(`#${property.id}`).css('display','inline-block'); }
          });
          if (propform_data != null) {
            change.reset();
            mod.obj.save(type, id, propform_data, (rellist==null)?null:rellist.table());
          }
          else {
            obtabs.showtab('_obTab0');
          }
        }),
        // -- Delete
        (id == null || !api_objtype.acl.delete)?null:$('<input/>', { class:'btn', type:'submit', value:'Delete'  }).on('click', function() {
          obAlert('<b>WARNING!:</b><br>This action wil permanently delete this object, are you sure you want to continue?',
          {
            Ok:function(){ content.append(loader.removeClass('fadein').addClass('fadein')); $.when( api('delete',`objecttype/${type}/object/${id}`) ).always(function() { mod.obj.list(type); }); },
            Cancel:null
          });
        }),
        // -- Close
        $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() {
          change.check(function() { mod.obj.list(type); });
        })
      ]
    };

    content.empty().append(new obContent(obcontent).html());
    loader.remove();
    change.observe();

  },

  /******************************************************************
   * mod.obj.save(type, id, properties, relations)
   * ===================================
   * Save object
   *    type        : Object type UUID
   *    id          : Object UUID
   *    properties  : Form data
   *    relations   : Relations list
   ******************************************************************/
   save: function(type, id, obj_config, rellist) {
    content.append(loader.removeClass('fadein').addClass('fadein'));

    let dtsave = {};
    let dtfile = new FormData;

    // File uploads
    $.each(obj_config, function(key, value) {
      if (key.startsWith('f_')) {
        let felem = $('#'+key);
        if (felem[0].files.length > 0) {
          dtfile.append(key, felem[0].files[0]);
        }
        else {
          dtfile.append(key, new File([''], ''));
        }
      }
      else {
        dtsave[key] = value;
      }
    });

    // Relations
    if (rellist != null) {
      dtsave['relations'] = [];
      $.each(rellist.html().find('tbody').children('tr'), function() {
        let tr = $(this);
        if (!tr.hasClass('delete')) {
          dtsave.relations = [...dtsave.relations, JSON.parse(tr.attr('hdt')).id];
        }
      });
    }

    // Save
    let sdat = (id == null)
      ? ['post', `objecttype/${type}/object`]
      : ['put', `objecttype/${type}/object/${id}`];
    $.when(api(sdat[0], sdat[1], dtsave)).always(function(apidata) {
      if (!!dtfile.entries().next().value) {
        if (sdat[0] == 'post') {
          sdat[1] = `objecttype/${type}/object/${apidata.id}`;
        }
        $.when(upload(sdat[1], dtfile)).done(function() {
          mod.obj.list(type);
        });
      }
      else {
        mod.obj.list(type);
      }
    });

  },


  /******************************************************************
   * mod.obj.properties
   * ==================
   * Array of properties and subarrays for gathering individual
   * property data
   ******************************************************************/
  properties: {

    /******************************************************************
     * mod.obj.properties.open_pwd(type, id, property, cpsrc)
     * ==================
     * Open the property popup
     *    type      : Object type UUID
     *    id        : Object UUID
     *    property  : Relations table
     *    cpsrc     : Copy's Source element
     ******************************************************************/

    open_pwd: function(type, id, property, cpsrc=null) {

      $.when(
        api('get',`objecttype/${type}/object/${id}/property/${property}`)
      ).done(function(apidata) {
        let value = '';
        if ('value' in apidata) {
          value = CryptoJS.AES.decrypt(apidata.value, (new obBase).decode(basebq)).toString(CryptoJS.enc.Utf8);
        }
        if (cpsrc!=null) {
          if (!window.isSecureContext) {
            obAlert('Copy to clipboard is only available on secure pages (https)');
          }
          else {
            navigator.clipboard.writeText(value);
            cpsrc.addClass('icack');
            setTimeout(function() { cpsrc.removeClass('icack'); }, 500);
          }
        }
        else {
          const fieldid = '757c8b7445742f39d04749bd3bdaa66d';
          let popup = new obPopup2({
            content: new obForm([{ id:fieldid, name:'Password', type:'string', value:value }]).html(),
            control: { 'Close (5)':null },
            size: { width:400, height:120 },
            autofocus: false
          });
          $('#_obTab0-content').append(popup.html());
          let pwfield = popup.html().find(`#${fieldid}`);
          pwfield
            .on('focus', function() { pwfield.select(); })
            .on('keydown', function(event) {
              if (event.ctrlKey && event.which == '67') {
                return true;
              }
              else if (event.which == '27') {
                popup.remove();
              }
              else {
                event.preventDefault();
                return false;
              }
            });
          pwfield.focus();
          let cntdown = 5;
          let btnClose = popup.html().find('input.btn');
          let autoclose = setInterval(function() {
            cntdown--;
            btnClose.val(`Close (${cntdown})`);
            if (cntdown === 0) {
              clearInterval(autoclose);
              popup.remove();
            }
          }, 1000);
        }
      });
    },

    /******************************************************************
     * mod.obj.properties.open_img(type, id, property)
     * ==================
     * Open the property popup
     *    type      : Object type UUID
     *    id        : Object UUID
     *    property  : Relations table
     ******************************************************************/

    open_img: function(type, id, property, target='', fn='default') {

      let img = $('<img/>', {
        src: `${apibase}/v2/objecttype/${type}/object/${id}/property/${property.id}/content?fn=${fn}&format=view`,
        style: 'max-width:600px; max-height:350px; position:absolute; top:50%; left:50%; transform:translate(-50%,-58%);',
        class: 'pointer'
      }).on('click', function(){
        window.open(`${apibase}/v2/objecttype/${type}/object/${id}/property/${property.id}/content?fn=${fn}&format=view`, '_blank');
        popup.remove();
      });
      let popup = new obPopup2({
        content: img,
        control: { 'Ok':null },
        size: { width:600, height:400 }
      });
      $(target).append(popup.html());
    }

  },

  /******************************************************************
   * mod.obj.relations
   * ==================
   * Array of relations and subarrays for adding and removing
   * related objects
   ******************************************************************/
  relations: {

    /******************************************************************
     * mod.obj.relations.open(type, id, rtable)
     * ==================
     * Open the relations form
     *    type    : Object type UUID
     *    id      : Object UUID
     *    table   : Relations table
     *    row     : Relation as selected in the table
     ******************************************************************/
    open: function(type, id, table, row, acl_update) {

      // Loader
      let popup_loader = new obPopup({
        content: loader.removeClass('fadein').addClass('fadein'),
        control: null
      });
      $('#_obTab1-content').append(popup_loader.html());

      let newrel = (row == null);

      // Select new relation
      if (newrel) {

        $.when(
          api('get',`/objecttype/${type}/object/${id}/relation/available?format=aggr`)
        ).done(function(relations) {

          // Filter out selected
          let relations_selected = [];
          $.each(table.html().children('tbody').find('tr'), function(idx, tr) {
            relations_selected = [...relations_selected, JSON.parse($(tr).attr('hdt')).id];
          });
          $.each(relations, function( idx, rec ) {
            if (rec.id == id) {
              delete relations[idx];
            }
            else if (relations_selected.indexOf(relations[idx].id) != -1) {
              delete relations[idx];
            }
            else {
              let colnr = 0;
              $.each(rec, function(key, value) {
                if (colnr > 6) {
                  delete relations[idx][key];
                }
                colnr++;
              });
              for (let i=colnr; i<=6; i++) {
                relations[idx][`dmy${i}`] = null;
              }
            }
          });

          // Clean up empty slots created by delete action
          relations = relations.flat();

          // Table
          let objlist = new obFTable({
            table: {
              id: '1eba292da5c6bd11e8b501c462cbf896ba76ee6a',
              data: relations,
              columns: [
                { id: 'rtp', name:'Type', orderable: true },
                { id: 'rf1', name:'Field', orderable: true },
                { id: 'rf2', name:'', orderable: false },
                { id: 'rf3', name:'', orderable: false },
                { id: 'rf4', name:'', orderable: false }
              ],
              columns_resizable: true,
              columns_hidden: ['id','objtype']
            },
            search: true,
            open:   function(td) {
              let tr = $(td).parent();
              let rdata = [];
              $.each(tr.find('td'), function(idx, field) {
                rdata = [...rdata, $(field).text()];
              });
              let rrow = table.addrow(rdata);
              rrow.attr('hdt', tr.attr('hdt'));
              popup.remove();
            },
            footer: 'Objects'
          });

          // Popup
          let popup = new obPopup({
            content: objlist.html(),
            control: $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { popup.remove(); })
          });
          popup.html().find('.tblwrap-control-search').removeClass('tblwrap-control-search').addClass('tblwrap-control-search-left');
          $('#_obTab1-content').append(popup.html());

          $.each(content.find('.obTable-tb'), function() { $(this).obTableRedraw(); });
          popup_loader.remove();

        });
      }

      // Show related object properties
      else {

        let hdt = JSON.parse(row.attr('hdt'));
        $.when(
          api('get',`objecttype/${hdt.objtype}/object/${hdt.id}?format=full`)
        ).fail(function() {
          popup_loader.remove();
        })
        .done(function(api_obj) {

          // Prepare
          let frmdata = [];
          $.each(api_obj, function(id, value) {
            if (id != 'id') {
              let field = { id:value.id, name:value.label, type:'string', value:value.value_text, readonly:true };
              if ($.inArray(value.type, [3, 4]) != -1) {
                field.type = "select";
                field.options = { 0:value.value_text };
                field.value = 0;
              }
              else if (value.type == 5) {
                field.type = "checkbox";
              }
              frmdata = [...frmdata, field];
            }
          });

          // Popup
          let popup = new obPopup({
            content: new obForm(frmdata).html(),
            control: [
              (!acl_update)?null:$('<input/>', { class:'btn', type:'submit', value:'Unassign' }).on('click', function() { row.addClass('delete'); popup.remove(); }),
              $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { popup.remove(); })
            ]
          });
          popup.html().find('.tblwrap-control-search').removeClass('tblwrap-control-search').addClass('tblwrap-control-search-left');
          $('#_obTab1-content').append(popup.html());
          popup_loader.remove();

        });

      }
    }
  }
}