$(document).ready(function() {
    var title=encodeURIComponent(document.title);
    var page=encodeURIComponent(document.location);
    var referrer=encodeURIComponent(document.referrer);
    var url="analytics.php?_page="+page+"&_title="+title+"&_referrer="+referrer+"&_menu="+encodeURIComponent(_menu)+"&_submenu="+encodeURIComponent(_submenu);
    $.get(url, function( data ) {
        if(data!='')
            console.log(data);
    });
});
