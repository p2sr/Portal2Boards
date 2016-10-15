<?php

class AggregatedView
{
    static $grey = false;

    static function getEntry($board, $player, $page, $entry, $convertToTime) {
        $playerData = $board[$player]["userData"]; $scoreData = $board[$player]["scoreData"] ?>
        <div class="entry
        <?php if (SteamSignIn::isLoggedIn($player)) {
            echo "you";
        } ?>"
        style="
        <?php if($scoreData["scoreRank"]  % 2 == 0) { ?>
            background: #d3d3d3
        <?php } ?>
        ">
            <div class="place"><?=$scoreData["playerRank"]?></div>
            <div class="profileIcon">
                <a href="/profile/<?=$player;?>">
                    <?php if ($playerData["avatar"] != NULL && $page == 1): ?>
                        <img src="<?=$playerData["avatar"]; ?>" alt=""/>
                    <?php elseif ($playerData["avatar"] != NULL && $page != 1): ?>
                        <img src="" avatar="<?=$playerData["avatar"]; ?>" alt=""/>
                    <?php else: ?>
                        <img src="" alt=""/>
                    <?php endif; ?>
                </a>
            </div>
            <div class="boardname"><a href="/profile/<?=$player;?>"><?=$playerData["boardname"]?></a></div>
            <?php if ($convertToTime): ?>
                <div class="score"><?= Leaderboard::convertToTime($scoreData["score"]) ?></div>
            <?php else: ?>
                <div class="score"><?=round($scoreData["score"])?></div>
            <?php endif; ?>
        </div>
    <?php }

    static function getPointEntry($board, $player, $page, $entry) {
        self::getEntry($board, $player, $page, $entry, false);
    }

    static function getTimeEntry($board, $player, $page, $entry) {
        self::getEntry($board, $player, $page, $entry, true);
    }
}