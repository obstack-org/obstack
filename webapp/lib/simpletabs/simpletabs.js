/******************************************************************
 * 
 * simpleTabs()         Function (init)
 * 
 * Example:
 *  var stabs = $('<div/>');
 *  $('#ParentDiv').append(stabs);
 *  stabs.simpleTabs({
 *    tabs: [
 *      { title: 'Tab 1', html: $('<div/>').html('Tab 1 Content') },
 *      { title: 'Tab 2', html: $('<div/>').html('Tab 2 Content') }
 *    ]
 *  });
 * 
 ******************************************************************/

(function($) {
    "use strict";

    // simpleTabs init
    $.fn.simpleTabs = function ( options ) {
        var pid = $(this)[0].id;   
        var out = $('<div/>', { id: pid+'_sTabs' });
        var tabs = $('<div/>', { id: pid+'_sTabs-tabs', 'class': 'sTabs-tabs' });
        var cont = $('<div/>', { id: pid+'_sTabs-content', 'class': 'sTabs-content' });
        $.each(options.tabs, function (key, value) {
            tabs.append(  
                $('<div/>', { id: pid+'_sTab'+key, 'class': 'sTabs-tab' })
                    .html(value.title)
                    .bind('click', function(event) {
                        sTabClick('#'+$(this)[0].id);
                    })
                );
            cont.append(
                $('<div/>', { id: pid+'_sTab'+key+'-content', 'class': 'sTabs-tab-content' })
                    .html(value.html)
            );
        });
        out.append(tabs);
        out.append(cont);        
        this.html(out);
        sTabClick('#'+pid+'_sTab0');
    }

    // simpleTab onclick
    function sTabClick(tabname) {
        $('.sTabs-tab').removeClass('active');
        $('.sTabs-tab-content').hide();
        $(tabname).addClass('active');
        $(tabname+'-content').show();

        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    }

})(jQuery);