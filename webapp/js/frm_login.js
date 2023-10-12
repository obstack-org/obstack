/******************************************************************
 *
 * frm
 *  .login
 *    .show()           Function
 *
 ******************************************************************/

frm['login'] = {

  /******************************************************************
   * frm.login.message
   * ===================
   * Login screen message
   ******************************************************************/

  message: $('<div/>', { class: 'overlay-login-inner-message' }),

  /******************************************************************
   * frm.login.show()
   * ===================
   * Generate login screen
   ******************************************************************/
  show: function() {
    // Login form
    let loginform = new obForm([
      { id:'username', name:'Username', type:'string' },
      { id:'password', name:'Password', type:'password' }
    ]);

    // Load and display login screen
    $(document.body).append(
      $('<div/>', { class: 'overlay' }).append(
        $('<div/>', { class: 'overlay-login' }).append(
          $('<div/>', { class: 'overlay-login-inner' }).append(
            frm.login.message,
            loginform.html().on('keypress', function(event) {
              if (event.keyCode === 13) {
                frm.login.login(loginform.validate());
              }
            }),
            $('<input/>', { class:'btn', type:'submit', value:'Submit' }).on('click', function() { frm.login.login(loginform.validate()); })
          )
        )
      )
    );
  },

  /******************************************************************
   * frm.login.login()
   * ===================
   * Validate and submit login request
   ******************************************************************/
  login: function(loginform_validate) {
    setTimeout(function() {
      $.when(
        api('post', 'auth', loginform_validate)
      ).fail(function() {
        frm.login.message.html('Incorrect username or password');
      }
      ).done(function(apidata) {
        if (apidata.active) {
          location.reload(true);
        }
      });
    },0);
  }

}