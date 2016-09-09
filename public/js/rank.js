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