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
    // Login form
    let loginform = new obForm([
      { id:'username', name:'Username', type:'string' },
      { id:'password', name:'Password', type:'password' }
    ]);
    let bntSubmit = $('<input/>', { class:'btn', type:'submit', value:'Submit' }).on('click', function() {
      $.when(
        api('post', 'auth', loginform.validate())
      ).fail(function() {
        loginmessage.html('Incorrect username or password');
      }
      ).done(function(apidata) {
        if (apidata.active) {
          location.reload(true);
        }
      });
    });

    // Load and display login screen
    let loginmessage = $('<div/>', { class: 'overlay-login-inner-message' });
    $(document.body).append(
      $('<div/>', { class: 'overlay' }).append(
        $('<div/>', { class: 'overlay-login' }).append(
          $('<div/>', { class: 'overlay-login-inner' }).append(
            loginmessage,
            loginform.html(), bntSubmit
          )
        )
      )
    );
  }

}