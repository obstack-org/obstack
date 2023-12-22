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
                event.preventDefault();
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
      ).fail(function(apidata) {
        // OTP
        if ('responseJSON' in apidata) {
          let otpform = new obForm([
            { id:'otp', name:'Enter your OTP authentication code:', type:'string' }
          ]);

          // Request OTP
          if ('otp' in apidata.responseJSON) {
            $(document.body).append(
              $('<div/>', { class:'overlay' }).append(
                $('<div/>', { class:'overlay-qr' }).append(
                  otpform.html().on('keypress', function(event) {
                    if (event.keyCode === 13) {
                      event.preventDefault();
                      frm.login.login_otp(loginform_validate, otpform.validate());
                    }
                  }),
                  $('<input/>', { class:'btn', type:'submit', value:'Verify' }).on('click', function() {
                    frm.login.login_otp(loginform_validate, otpform.validate());
                  })
                )
              )
            );
            jQuery('#qrcodeTable').qrcode({
              render	: "table",
              text	: apidata.responseJSON.uri
            });
          }

          // Generate QR
          if ('uri' in apidata.responseJSON) {
            $(document.body).append(
              $('<div/>', { class:'overlay' }).append(
                $('<div/>', { class:'overlay-qr' }).append(
                  'Configure your two-factor application by<br> scanning the following QR code.',
                  '<br><br>Recommended: ', $('<a/>', { class:'link', html:'FreeOTP', href:'https://freeotp.github.io', target:'_freeotp' }),'<br>',
                  $('<div/>', { id:'qrcodeTable', style:'margin:20px 70px 20px 70px;' }),
                  otpform.html().on('keypress', function(event) {
                    if (event.keyCode === 13) {
                      event.preventDefault();
                      frm.login.login_otp(loginform_validate, otpform.validate());
                    }
                  }),
                  $('<input/>', { class:'btn', type:'submit', value:'Verify' }).on('click', function() {
                    frm.login.login_otp(loginform_validate, otpform.validate());
                  })
                )
              )
            );
            jQuery('#qrcodeTable').qrcode({
              render	: "table",
              text	: apidata.responseJSON.uri
            });
          }

        }
        else {
          frm.login.message.html('Incorrect username or password');
        }
      }
      ).done(function(apidata) {
        if (apidata.active) {
          location.reload(true);
        }
      });
    },0);
  },

  login_otp: function(loginform, otpform) {
    loginform.otp = otpform.otp;
    $.when(
      api('post', 'auth', loginform)
    ).always(function() {
      location.reload(true);
    });
  }

}