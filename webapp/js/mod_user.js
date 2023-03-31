/******************************************************************
 * 
 * mod
 *  .user
 *    .self           Object
 *    .list()         Function
 *    .open()         Function
 *    .open_htgen()   Function
 *    .save()         Function
 *    .token          Object
 *      .open()       Function
 * 
 ******************************************************************/

mod['user'] = {

  /******************************************************************
   * mod.user.self
   * ====================
   * Object containing active user info (for display only)
   ******************************************************************/
  self: {},

  /******************************************************************
   * mod.user.list()
   * ===================
   * List users
   ******************************************************************/
  list: function() {
    // Generate HTML with loader
    var controlbar = $('<div/>', { class: 'content-wrapper-control' });
    var dtobjtypes = new datatable();
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html('Users'),
      $('<div/>', { class: 'content-wrapper' }).append(
        dtobjtypes.html(),
        controlbar
      )
    );
    content.append(loader);

    // Load and display data
    $.when( 
      api('get','auth/user')
    ).done(function(apidata) {
      $.each(apidata, function(index, value) {
        apidata[index]['active'] = '&nbsp;&nbsp;&nbsp;&nbsp;'+htbool(apidata[index]['active']);
        apidata[index]['tokens'] = '&nbsp;&nbsp;&nbsp;&nbsp;'+htbool(apidata[index]['tokens']);
        apidata[index]['sa'] = '&nbsp;&nbsp;&nbsp;&nbsp;'+htbool(apidata[index]['sa']);
      }); 
      dtobjtypes.apidata(apidata);
      dtobjtypes.options({
        aaSorting: [],
        columns: [{title:'[id]'}, {title:'Username'}, {title:'First name'}, {title:'Last name'}, {title:'Active'}, {title:'Tokens'}, {title:'Admin'}],
        columnDefs: [
          { targets: '_all',  className:  'dt-head-left', orderable: true },            
          { targets: [0], visible: false },
          { targets: [0], searchable: false },
          { targets: [4,5,6], width: '40px' }
        ]
      });
      dtobjtypes.create();   
      dtobjtypes.html().on('click', 'tr', function () {
        if (typeof dtobjtypes.table().row(this).data() != 'undefined') {
          mod.user.open(dtobjtypes.table().row(this).data()[0]);
        }
      });
      controlbar.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
          .on('click', function(event) {
            mod.user.open(null);
          })
      );
      loader.remove();
    });
  },

  /******************************************************************
   * mod.user.open(id)
   * ===================
   * Open user or own profile
   *    id    : User UUID, null for own profile
   ******************************************************************/
  open: function(id) {
    // New
    if (id == null) {
      mod.user.open_htgen(null, { active:true }, {});
    }
    // Open
    else {
      $.when(
        api('get',`auth/user/${id}`),
        api('get',`auth/user/${id}/token`)
      ).done(function(api_user, api_tokens) {
        mod.user.open_htgen(id, api_user[0], api_tokens[0]);
      });
    }
  },

  /******************************************************************
   * mod.user.open_htget()
   * ===================
   * Generate page from API data
   *    id      : User UUID, null for own profile
   *    api_..  : API data
   ******************************************************************/
  open_htgen: function(id, api_user, api_tokens) {
    // Generate HTML with loader
    var stabs = $('<div/>', { class: 'content-tab' });
    var cname = $('<div/>');
    var controlbar = $('<div/>', { class: 'content-wrapper-control-right' });
    var settings = $('<form/>');
    var tokens = new datatable();
    var tokens_controls = $('<div/>', { class: 'content-wrapper-tab-control' });
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(cname), 
      $('<div/>', { class: 'content-wrapper' }).append(stabs, controlbar)
    );
    var stabs_tabs = [
      { title: 'Profile',  html: $('<div/>', { class: 'content-tab-wrapper' }).append(settings) }
    ];

    // Load and display data
    let self = (id == 'self');
    if (self) {
      cname.append('Profile');
    }
    else {
      cname.append(
        $('<a/>', {
          class: 'link',
          html: 'Users',
          click: function() { mod.user.list(); }
        }),
        ` / ${api_user.username}`
        );
    }
    if (api_user.tokens) {
      stabs_tabs = [...stabs_tabs, { title: 'Tokens',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(tokens.html(), tokens_controls) }];
    }
    stabs.simpleTabs({
      tabs: stabs_tabs
    });

    // Format and load form fields
    jfpasswd = '';
    if (id != null) {
      jfpasswd = '•••••••';
    }
    jfschema = { 
      username: { title: 'Name',  type: 'string', default: api_user.username, readonly:(id!=null) },
      password: { title: 'Password', type: 'password', default:jfpasswd },
      passvrfy: { title: 'Password (verify)', type: 'password', default:jfpasswd }
    }
    if (!self) {
      jfschema['firstname'] = { title: 'First name',  type: 'string',  default: api_user.firstname };
      jfschema['lastname']  = { title: 'Last name',   type: 'string',  default: api_user.lastname };
      jfschema['active']    = { title: 'Active',      type: 'boolean', default: api_user.active };
      jfschema['tokens']    = { title: 'Tokens',      type: 'boolean', default: api_user.tokens };
      jfschema['sa']        = { title: 'Admin',       type: 'boolean', default: api_user.sa };
    }
    settings.jsonForm({
      schema: jfschema,
      form: ['*']
    });

    // Add onclick for empty password field
    settings.find(':input').each(function() {
      if ($(this).prop('name').substring(0,4) == 'pass') {
        $(this).on('click', function(event) { 
          if ($(this).val() == '•••••••') {
            $(this).val('');
          }
        });
      }
    });

    // Add object type buttons
    controlbar.append( $('<input/>', { class: 'btn', type: 'submit', value: 'Save'  }).on('click', function(event) { mod.user.save(id, settings); }) );
    if ((id != null) && (id != 'self')) {
      controlbar.append( $('<input/>', { class: 'btn', type: 'submit', value: 'Delete'  }).on('click', function(event) { 
        if (confirm('Are you sure you want to delete this user?')) {
          $.when( api('delete',`auth/user/${id}`) ).always(function() { mod.user.close(); });
        }
      }));
    }
    controlbar.append( $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { mod.user.close(id); }) );

    // Populate tokens table
    $.fn.dataTable.ext.errMode = 'none';
    tokens.apidata(api_tokens);
    tokens.options({ 
      columns: [{title:'[id]'}, {title:'Name'}, {title:'Expires'}],
      columnDefs: [...tokens.getoptions().columnDefs, { targets:[0], visible:false }]
    }); 
    tokens.create();

    // Click event for value table
    tokens.html().on('click', 'tr', function () {
      if (typeof tokens.table().row(this).data() != 'undefined') {
        mod.user.token.open(id, tokens.table().row(this));
      }
    });

    // Add properties buttons
    tokens_controls.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
        .on('click', function(event) {
            mod.user.token.open(id, tokens);
        })
    );
  },

  /******************************************************************
   * mod.user.save(id, settings)
   * ===================
   * Save profile
   *    id        : User UUID, null for own profile
   *    settings  : Form data
   ******************************************************************/
  save: function(id, settings) {
    // Prepare data formats 
    let dtsave = {};
    let frmpassword = null;
    let frmpassvrfy = null;

    // Gather data from settings fields
    settings.find(':input').each(function() {
      if ($(this).prop('type') == 'checkbox') {
        if ($(this).prop('checked')) { dtsave[$(this).prop('name')] = 1; }
        else { dtsave[$(this).prop('name')] = 0; }
      }
      else {
        dtsave[$(this).prop('name')] = $(this).prop('value');
        if ($(this).prop('name') == 'password') { frmpassword = $(this); }
        if ($(this).prop('name') == 'passvrfy') { frmpassvrfy = $(this); }
      }
    });

    // Form validity checks
    let save = true;
    if (dtsave.password.length < 7) {
      save = false;
      frmpassword.prop('style', 'box-shadow: 0 0 5px red');
      $('.sTabs-tab').removeClass('active');
      $('.sTabs-tab-content').hide();
      $('#_sTab0').addClass('active');
      $('#_sTab0-content').show();
    }
    else {
      frmpassword.prop('style', '');
    }
    if (dtsave.password != dtsave.passvrfy) {
      save = false;
      frmpassvrfy.prop('style', 'box-shadow: 0 0 5px red');
      $('.sTabs-tab').removeClass('active');
      $('.sTabs-tab-content').hide();
      $('#_sTab0').addClass('active');
      $('#_sTab0-content').show();
    }

    // Send to API    
    if (save) {
      delete dtsave.passvrfy;
      if (dtsave.password == '•••••••') { delete dtsave.password }
      if (id == null) {
        $.when( api('post',`auth/user`,dtsave) ).always(function() { mod.user.close(); });
      }
      else {
        delete dtsave.username;
        $.when( api('put',`auth/user/${id}`,dtsave) ).always(function() { mod.user.close(); });
      }
    }
  },

  close: function(id) {
    if (id == 'self') {
      $.when(
        api('get','objecttype')
      ).done(function(objecttypes) {
        if (objecttypes.length >= 1) {
          mod.obj.list(objecttypes[0].id);
        }
      });
    }
    else {
      mod.user.list();
    }
  },

  /******************************************************************
   * mod.user.token
   * ==============
   * Array of functions and subarrays for editing tokens
   ******************************************************************/ 
  token: {
    
    /******************************************************************
     * mod.user.token.open(id, vtable)
     * ===============================
     * Open the value form
     *   id       : User UUID, null for own profile
     *   vtable   : Value/Token as selected in the table
     ******************************************************************/
    open: function(id, vtable) {
      let frmnewrec = (vtable.constructor.name == 'datatable');

      // Generate HTML with loader
      let btnsubmit = 'Ok';
      if (frmnewrec) {
        btnsubmit = 'Create';
      }
      let vform = $('<form/>');      
      var overlay = $('<div/>');
      let popup_wrapper = $('<div/>', { class: 'content-popup-wrapper content-popup-wrapper_medium' });
      let popup_control = $('<div/>', { class: 'content-popup-wrapper-control' });
      $('#_sTab1').append(overlay);
      overlay.append(
        $('<div/>', { class: 'content-popup-overlay' }).append(
          popup_wrapper.append(popup_control, vform)
        )
      );

      // Ok/Create button
      popup_control.append(        
        $('<input/>', { class: 'btn', type: 'submit', value: btnsubmit }).on('click', function(event) {
          // Prepare form data
          let fdata = {};
          vform.find(':input').each(function() {
            fdata[$(this).prop('name')] = $(this).prop('value');
          });
          // Prepare row data
          let rdata = [
            fdata.name, 
            fdata.expiry.replace('T',' ')
          ];

          // Check date format and submit
          if (!rdata[1].match(/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$/)) {
            vform.find(':input').each(function() {
              if ($(this).prop('name') == 'expiry') {
                $(this).prop('style', 'box-shadow: 0 0 5px red');
              }
            });            
          }
          else {
            if (frmnewrec) {
              // Create token
              $.when( 
                api('post',`auth/user/${id}/token`, { name:rdata[0], expiry:rdata[1] }) 
              ).done(function(token) {
                vtable.addrow([token.id, ...rdata]);
                popup_wrapper.removeClass('content-popup-wrapper_medium').addClass('content-popup-wrapper_small');
                vform.empty().jsonForm({
                  schema: { 'token':   {title:'Token: (only provided once)', type:'string', default:token.token, readonly:true } },
                  form: ['*']
                });
                popup_control.empty().append(
                  $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { overlay.remove(); })
                );
              });
            }
            else {
              // Update token
              $.when( 
                api('put',`auth/user/${id}/token/${vtable.data()[0]}`, { name:rdata[0], expiry:rdata[1] }) 
              ).done(function() {
                vtable.data([vtable.data()[0], ...rdata]);
                overlay.remove();                
              });
            }
          }
        })
      );

      // Delete button
      if (!frmnewrec) {
        popup_control.append( 
          $('<input/>', { class: 'btn', type: 'submit', value: 'Delete' }).on('click', function(event) {
            if (confirm('WARNING!: Token will be deleted instantly. Are you sure you want to continue?')) {
              $.when( 
                api('delete',`auth/user/${id}/token/${vtable.data()[0]}`) 
              ).done(function() {
                vtable.remove().draw();
                overlay.remove();                
              });
            }
          })
        );
      }

      // Close button
      popup_control.append( 
        $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { overlay.remove(); })
      );

      // Generate form
      vform.jsonForm({
        schema: { 
          'name':   {title:'Name', type:'string'},
          'expiry': {title:'Expires', type:'datetime-local'}
        },
        form: ['*']
      });      

      // Load data into form for editing
      if (!frmnewrec) {
        vform.find('input[name=name]').val(vtable.data()[1]);
        vform.find('input[name=expiry]').val(vtable.data()[2]);
      }
    }
  }

}