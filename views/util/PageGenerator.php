<?php

class PageGenerator {

    static function generatePages($board, $entryCallback, $maxEntriesShown = PHP_INT_MAX, $entriesPerPage = 40) {
        // A board that doesn't exist (e.g. an unknown chapter) shows no entries.
        $keys = array_keys($board ?? array());
        $numEntries = min($maxEntriesShown, count($keys));
        $pages = ceil($numEntries / $entriesPerPage);
        $page = 1;
        ?><div class="entries pages">
            <?php while ($page <= $pages): ?>
                <div
                <?php if ($page != 1): ?>
                    class="datatable page-entries" style="display:none"
                <?php else: ?>
                    class="datatable page-entries active"
                <?php endif; ?>
                >
                <?php $i = ($entriesPerPage * ($page - 1)) + 1;?>
                    <?php while (($page == $pages) ? ($i <= $numEntries) : ($i <= ($entriesPerPage * $page))): ?>
                        <?php $entryCallback($board, $keys[$i-1], $page, $i) ?>
                    <?php $i++; endwhile; ?>
                </div>
            <?php $page++; endwhile; ?>
        </div><?php
    }

}
