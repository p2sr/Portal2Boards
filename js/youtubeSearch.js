$(document).ready(function() {
    $(".youtube_icon").click(function(event) {
        event.stopPropagation();
        var chamber = $(this).parent().parent().parent().parent();
        var time = ($(chamber).find(".portalamount").length ? $(chamber).find(".portalsused:eq(0)").text().trim().toLowerCase() : $(chamber).find(".score:eq(0)").html());
        var chamber_name = $(chamber).find(".titlebg a:eq(0)").html();
        var c_name = chamber_name.replace(" ", "+").replace(/[0-9]/g, '');
        var url = "https://www.youtube.com/results?search_query=portal+2+"+c_name+"+"+time;
        window.open(url, '_blank');
    })
});