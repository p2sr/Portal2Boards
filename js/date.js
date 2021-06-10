var locale = moment.tz.guess();

function formatDateDifferenceFromNow($element, date, showSuffix) {
    var serverDate = getServerDate(date);
    var serverCurrentDate = getCurrentServerDate();
    formatDateDifference($element, serverDate, serverCurrentDate, showSuffix);
}

function getDateDifferenceColorFromNow(date, cutoff) {
    var serverDate = getServerDate(date);
    var serverCurrentDate = getCurrentServerDate();
    return getDateDifferenceColor(serverDate, serverCurrentDate, cutoff);
}

function formatDateDifference($element, moment1, moment2, showSuffix) {
    var color = getDateDifferenceColor(moment1, moment2);
    $element.css('color', color);
    $element.text(moment1.from(moment2, !showSuffix));
}

function getDateDifferenceColor(moment1, moment2, cutoff) {
    if (typeof(cutoff) === 'undefined')
        cutoff = false;

    var passedHours = -moment1.diff(moment2, 'hours');
    var hourScale =  d3.scaleLinear()
        .domain([0, 24, 14 * 24, 2 * 30 * 24])
        .range(['#2eb82e', '#258e25', '#cca300', '#e67300']);
    var color = null;
    if (passedHours <= 2 * 30 * 24) {
        color = hourScale(passedHours);
    }
    else if (!cutoff){
        color = getOldDateColor();
    }
    return color;
}

function getOldDateColor() {
    return "#999"
}

function formatDate(date, formatString) {
    if (date !== undefined) {

        return formatMoment(moment(date), formatString);
    }
    return null;
}

function formatMoment(mom, formatString) {
    if (mom !== undefined) {

        if (formatString == undefined)
            formatString = "YYYY-MM-DD HH:mm:ss";

        return mom.format(formatString);
    }
    return null;
}

function localizeDate(date, formatString) {
    var serverDate = getServerDate(date);
    var dateTranslatedLocal = serverDate.clone().tz(getLocale());
    return formatDate(dateTranslatedLocal, formatString);
}

function realDate(date) {
    return (date !== undefined && date !== "" && date !== null);
}

function getServerDate(date) {
    //return moment.utc(date);
    return moment.tz(date, "Europe/Amsterdam");
}

function getCurrentServerDate() {
    //return moment().utc();
    return moment().tz("Europe/Amsterdam");
}

function getCurrentLocalDate(formatString) {
    return formatMoment(moment(), formatString);
}

function getLocale() {
    if (locale !== null && locale !== undefined && locale !== "")
        return locale;
    else
        return "Etc/UTC";
}