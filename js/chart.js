function drawActivityChart(activityByDate, beginDate, endDate, $element, resize) {

    if (resize == undefined)
        resize = true;

    var activityByDay = {};

    var firstDay = moment(formatDateAsDay(beginDate));
    var lastDay = moment(formatDateAsDay(endDate));

    var days = Math.ceil(lastDay.diff(firstDay, 'days', true)) + 1;
    for (var d = 0; d < days; d++) {
        var thisDay = moment(firstDay).add(d, 'days'); //duplicating the moment object
        activityByDay[formatDateAsDay(formatMoment(thisDay))] = {
            changes: [],
            updates: 0
        };
    }

    /* localize dates of changelog entries*/
    for (var date in activityByDate) {
        var day = localizeDateAsDay(date);
        if (activityByDay[day] === undefined) {
            activityByDay[day] = {
                changes: [],
                updates: 0
            }
        }
        for (var c in activityByDate[date].changes) {
            var change = activityByDate[date].changes[c];
            change.date = localizeDate(date);
            activityByDay[day].changes.push(change);
        }
        activityByDay[day].updates += activityByDate[date].updates;
    }

    for (var d in activityByDay) {
        var changes = activityByDay[d].changes;
        activityByDay[d].changes = changes;
    }

    /* generating simple array*/
    var dayUpdates = [];
    for (var d in activityByDay) {
        dayUpdates.push({
            day: d,
            changes: activityByDay[d].changes,
            updates: activityByDay[d].updates
        });
    }

    /* sorting for ascending dates*/
    dayUpdates = dayUpdates.sort(function(a, b) {
        var dayA = new Date(a.day);
        var dayB = new Date(b.day);
        return  dayA < dayB ? -1 : 1;
    });

    var dayBins = [];
    var numBins = Math.min(dayUpdates.length, Math.ceil($element.width() / 10));
    var binSize = Math.floor(dayUpdates.length / numBins);
    numBins += Math.ceil((dayUpdates.length - (numBins * binSize)) / binSize);

    for (var i = 0; i < numBins; i++) {
        var binData = {};
        binData.startDay = dayUpdates[(i * binSize)].day; /*first day*/
        binData.endDay = dayUpdates[Math.min(dayUpdates.length - 1, ((i + 1) * binSize) - 1)].day; /* last day */
        binData.updates = 0;
        binData.changes = [];
        for (var j = 0; j < binSize; j++) {
            if ((i * binSize) + j === dayUpdates.length) /*last bin*/
                break;

            var dayData = dayUpdates[(i * binSize) + j];
            binData.changes = binData.changes.concat(dayData.changes);
            binData.updates += dayData.updates;
        }
        binData.changes = binData.changes.sort(function(a, b) {
            var rankA = a.changeData.post_rank;
            var rankB = b.changeData.post_rank;
            if (rankA == rankB) {
                var dateA = moment(a.changeData.time_gained);
                var dateB = moment(b.changeData.time_gained);
                return (dateA.isAfter(dateB)) ? -1 : 1;
            }
            else if (rankA == null)
                return 1;
            else if (rankB == null)
                return -1;
            else
                return  rankA < rankB ? -1 : 1;
        });
        dayBins.push(binData);
    }

    //var barSizeRatio = dayBins.length > 100 ? 1 : (dayBins.length < 60 ? 0.9 : 0.75);
    var barSizeRatio = 0.75;

    function drawChart() {
        $element.empty();
        new Morris.Bar({
            element: $element.attr("id"),
            data: dayBins,
            barSizeRatio: barSizeRatio,
            xkey: 'startDay',
            ykeys: ['updates'],
            labels: ['Score updates'],
            gridTextSize: 11,
            xLabels: "day",
            hoverCallback: function (index, options, content) {
                var data = options.data[index];
                var str = "";
                if (data.startDay == data.endDay)
                    str += data.startDay;
                else
                    str += "From " + data.startDay + " to " + data.endDay;

                str += "<br>" + data.updates + " score update" + ((data.updates != 1) ? "s" : "") + " <br><br>";
                str += "<table>";

                var i = 0;
                var shownMaps = {};
                for (var c in data.changes) {
                    if (i >= 5) {
                        break;
                    }
                    var change = data.changes[c];

                    var players = change.players.sort();
                    var playerStr = "";
                    for (var p in players) {
                        var player = players[p];
                        if (playerStr != "")
                            playerStr += " & ";

                        playerStr += player;
                    }

                    var chamber = change.changeData.chamberName;
                    if (shownMaps[playerStr] != undefined) {
                        if (shownMaps[playerStr][chamber] != undefined) {
                            continue;
                        } else {
                            shownMaps[playerStr][chamber] = true;
                        }
                    } else {
                        shownMaps[playerStr] = {};
                        shownMaps[playerStr][chamber] = true;
                    }

                    var rank = (change.changeData.post_rank != null) ? change.changeData.post_rank : "";
                    var $rank = $("<div class='rank'>"+rank+"</div>");
                    drawRank($rank);
                    str +=
                        "<tr style='line-height: 20px'>" +
                            "<td>" + playerStr + "</td>" +
                            "<td align='center'>" + formatScoreTime(change.changeData.score) + "</td>" +
                            "<td align='center'>" + $rank.prop('outerHTML') + "</td>" +
                            "<td>" + chamber + "</td>" +
                        "</tr>";
                    i++;
                }

                str += "</table>";
                $(".morris-hover").html(str);
            },
            barColors: ['#2f96d1'],
            hideHover: 'auto',
            gridTextFamily: "Segoe UI",
            gridTextColor: "#777"
        });
    }

    drawChart();

    if (resize) {
        $(window).resize(function () {
            drawChart();
        });
    }
}

function getActivityByDate(activityByScore) {
    var changesByDate = {};

    for (var date in activityByScore) {
        var dateData = activityByScore[date];

        if (changesByDate[date] == undefined)
            changesByDate[date] = {};

        if (changesByDate[date]["changes"] == undefined)
            changesByDate[date]["changes"] = [];

        var scoreUpdates = 0;
        for (var act in dateData) {
            var activity = dateData[act];
            var change = {};
            change["changeData"] = activity["changeData"];
            change["changeData"]["post_rank"] = (activity["changeData"]["post_rank"] != null) ? parseInt(activity["changeData"]["post_rank"]) : null;
            change["changeData"]["pre_rank"] = (activity["changeData"]["pre_rank"] != null) ? parseInt(activity["changeData"]["pre_rank"]) : null;
            change["players"] = [];
            for (var p in activity["players"]) {
                var player = activity["players"][p];
                scoreUpdates++;
                change["players"].push(player);
            }
            changesByDate[date]["changes"].push(change);
        }
        changesByDate[date]["updates"] = scoreUpdates;
    }

    return changesByDate;
}

function getActivityByScore(changelog) {
    if(changelog != null ? changelog.length > 0 : false) {
        var activity = {};
        for (var c in changelog) {
            var change = changelog[c];

            if (change["time_gained"] == null) {
                continue;
            }

            if (activity[change["time_gained"]] == undefined)
                activity[change["time_gained"]] = {};
            if (activity[change["time_gained"]][change["mapid"]] == undefined)
                activity[change["time_gained"]][change["mapid"]] = {};
            if (activity[change["time_gained"]][change["mapid"]][change["score"]] == undefined)
                activity[change["time_gained"]][change["mapid"]][change["score"]] = {};
            if (activity[change["time_gained"]][change["mapid"]][change["score"]]["players"] == undefined)
                activity[change["time_gained"]][change["mapid"]][change["score"]]["players"] = [];

            activity[change["time_gained"]][change["mapid"]][change["score"]]["changeData"] = change;
            activity[change["time_gained"]][change["mapid"]][change["score"]]["players"].push(change["player_name"]);
        }

        var activitySimple = {};
        for (var date in activity) {
            if (activitySimple[date] == undefined)
                activitySimple[date] = [];

            for (var map in activity[date]) {
                for (var score in activity[date][map]) {
                    var act = activity[date][map][score];
                    activitySimple[date].push(act);
                }
            }
        }
        return activitySimple;
    }
    else {
        return {};
    }
}

function getDateFirstChange(activityByDate) {
    var keys = Object.keys(activityByDate);
    return formatDate(keys[keys.length - 1]);
}

function localizeDateAsDay(date) {
    return localizeDate(date, "YYYY-MM-DD");
}

function formatDateAsDay(date) {
    return formatDate(date, "YYYY-MM-DD");
}
