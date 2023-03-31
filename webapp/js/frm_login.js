/******************************************************************
 * 
 * frm
 *  .login
 *    .show()           Function
 * 
 ******************************************************************/

frm['login'] = {

  /******************************************************************
   * frm.login.show()
   * ===================
   * Generate login screen
   ******************************************************************/
  show: function() {
    // Load and display login screen
    var loginform = $('<form/>');
    var loginmessage = $('<div/>', { class: 'overlay-login-inner-message' });
    $(document.body).append(
      $('<div/>', { class: 'overlay' }).append(
        $('<div/>', { class: 'overlay-login' }).append(
          $('<div/>', { class: 'overlay-login-inner' }).append(
            loginmessage,
            loginform
          )
        )
      )
    );
    loginform.jsonForm({
      schema: { 
        'username': { type: 'string', title: 'Username' },
        'password': { type: 'password', title: 'Password' }
      },
      onSubmit: function (errors, values) {          
        $.when( 
          api('post', 'auth', {'username':values.username, 'password':values.password })
        ).fail(function() {
          loginmessage.html('Incorrect username or password');
        }
        ).done(function(apidata) {
          if (apidata.active) {
            location.reload(true);
          }
        });        
      }
    });
  }

}