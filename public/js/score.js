function formatScoreTime(time) {
    time = Math.abs(time);
    time = Math.round(time);
    var hundreds = (time % 100);
    var totalSeconds = Math.floor(time / 100);
    var minutes = Math.floor(totalSeconds / 60);
    var seconds = totalSeconds % 60 ;

    if (seconds < 10 && minutes > 0)
        seconds = "0"+seconds.toString();
    if (hundreds < 10)
        hundreds = "0"+hundreds.toString();

    if (minutes > 0) {
        return minutes + ":" + seconds + "." + hundreds;
    } else {
        return seconds + "." + hundreds;
    }
}

function getScoreFromString(str) {

    if (!str.match(/^[0-9.:]+$/g) ) {
        return null;
    }

    var hours = 0;
    var minutes = 0;
    var seconds = 0;
    if (str.indexOf(":") != -1) {
        var minutesOrHours = parseInt(str.split(":")[0]);
        str = str.split(":")[1];
        if (str.indexOf(":") != -1) {
            hours = minutesOrHours;
            minutes = parseInt(str.split(":")[0]);
            str = str.split(":")[1];
        } else {
            minutes = minutesOrHours;
        }
    }

    if (str.indexOf(".") != -1) {
        seconds = parseInt(str.split(".")[0]);
        str = str.split(".")[1];
    } else {
        return null;
    }

    var hundredths = 0;
    var hundredthsStr = str.split(".")[0];
    if (hundredthsStr.length == 1) {
        hundredths = 10 * parseInt(hundredthsStr);
    } else if (hundredthsStr.length == 2) {
        hundredths = parseInt(hundredthsStr);
    } else {
        return null;
    }

    return hundredths + (100 * seconds) + (100 * 60 * minutes) + (100 * 60 * 60 * hours);
}