//note that page indexes are used here and thus start at 0;
function nextPage($pageParent) {
  var index = $pageParent.find(".datatable.active").index();
  var lastPage = getLastPageIndex($pageParent);

    if (index == -1 && lastPage == -1) {
        return 0;
    }
    if (index == lastPage) {
        showPage(0, $pageParent);
        return 1;
    } else {
        showPage(index + 1, $pageParent);
        return index + 2;
    }
}

function previousPage($pageParent) {
    var index = $pageParent.find(".datatable.active").index();
    var lastPage = getLastPageIndex($pageParent);
    if (index == -1 && lastPage == -1) {
        return 0;
    }
    if (index == 0) {
        showPage(lastPage, $pageParent);
        return lastPage + 1;
    } else {
        showPage(index - 1, $pageParent);
        return index;
    }
}

function showPage(page, $pageParent, fade) {
    if (typeof(fade) === 'undefined')
        fade = true;

    $oldDataTable = $pageParent.find(".datatable");
    $newDataTable = $pageParent.find(".datatable:eq(" + page + ")");

    $newDataTable.find(".profileIcon a img").each(function() {
        var avatar = $(this).attr("avatar");
        if (avatar !== undefined) {
            $(this).attr("src", avatar);
        }
    });

    if (fade) {
        $oldDataTable.fadeOut(100);
        $oldDataTable.removeClass("active");
        $newDataTable.addClass("active");
        setTimeout(function () {
            $newDataTable.fadeIn(100);
        }, 100);
    } else {
        $oldDataTable.hide();
        $oldDataTable.removeClass("active");
        $newDataTable.addClass("active");
        $newDataTable.show();
    }
}

function getLastPageIndex($pageParent) {
    return $pageParent.find(".datatable").length - 1;
}

function getLastPage($pageParent) {
    return $pageParent.find(".datatable").length;
}