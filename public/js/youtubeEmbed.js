function embedOnBody(youtubeID, description) {
    $("#youtube").remove();

    if (description != "") {
        description = "<div id='description'>" + description + "</div>";
    }

    var $yt = $(
        "<div id='youtube'>" +
            "<div id='descriptionContainer'>" +
                description +
                "<div id='ytclose'>" +
                "<i class='fa fa-lg fa-times' aria-hidden='true'></i> " +
                "</div>" +
            "</div>" +
            "<iframe id='ytplayer' allowfullscreen='allowfullscreen' type='text/html' width='640' height='360' align='middle' src='https://www.youtube.com/embed/"+youtubeID+"&autoplay=1' frameborder='0'>" +
            "</iframe>" +
        "</div>");
    $("body").append($yt);

    $("#ytclose").click(function() {
        $("#youtube").remove();
    });
}

function embedInDiv($element, youtubeID) {
    $("#youtube").remove();
    var $yt = $("<iframe id='ytplayer' allowfullscreen='allowfullscreen' type='text/html' width='100%' height='100%' align='middle' src='https://www.youtube.com/embed/"+youtubeID+"&autoplay=1' frameborder='0'></iframe>");
    $element.append($yt);
}