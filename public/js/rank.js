function drawRank($element) {
    var scale = d3.scaleLinear()
        .domain([0, 10, 20, 50, 200, 500])
        .range(['darkviolet', 'blue', '#28a428', 'darkorange', '#cc0000', 'darkred']);

    var rank = $element.text();
    if (!isNaN(rank)) {

        rank = parseInt(rank);
        if (rank == 1) {
            $element.html("<i class='fa fa-trophy' aria-hidden='tru'></i>");
            $element.css("color", "#d980ff");
        }
        else if (rank <= 500) {
            $element.css('color', scale(rank));
        }
        else {
            $element.css('color', "#888888");
        }
    }
}

function getPointsFromRank(rank) {
    if(rank > 200){
        return 0;
    }
    var points = Math.max(1,  Math.pow(200 - (rank - 1), 2) / 200);
    return Math.round(points * 10) / 10;
}

function pointsOnHover($element, rank, options) {

    var points = getPointsFromRank(rank);

    if (!isNaN(rank)) {

        var opt = {
            trigger: 'manual',
            title: points + " " + (points == 1 ? "point" : "points")
        };

        if (options !== undefined) {
            Object.keys(options).forEach(function(key) {
                opt[key] = options[key];
            });
        }

        $element.tooltip(opt);
        $element.hover(function() {
            $(this).tooltip('show');
        }, function() {
            $(this).tooltip('hide');
        });
    }

}