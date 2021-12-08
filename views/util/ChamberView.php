<?php

class ChamberView
{

    static function getEntry($board, $player, $page)
    {
        $playerData = $board[$player]["userData"];
        $scoreData = $board[$player]["scoreData"] ?>
        <div class="entry
        <?php if (SteamSignIn::isLoggedIn($player)) {
            echo "you";
        } ?>"
        <?php if($scoreData["scoreRank"] % 2 == 0) { ?>
            style="background: #d6d6d6"
        <?php } ?>
        >
            <div class="rank"><?= $scoreData["playerRank"] ?></div>
            <div class="profileIcon">
                <a href="/profile/<?= $player; ?>">
                    <?php if ($playerData["avatar"] != NULL && $page == 1): ?>
                        <img src="<?=$playerData["avatar"]; ?>" alt=""/>
                    <?php elseif ($playerData["avatar"] != NULL && $page != 1): ?>
                        <img src="" avatar="<?=$playerData["avatar"]; ?>" alt=""/>
                    <?php else: ?>
                        <img src="" alt=""/>
                    <?php endif; ?>
                </a>
            </div>
            <div class="boardname"><a href="/profile/<?= $player; ?>"><?= $playerData["boardname"] ?></a></div>
            <div class="submission">
                <?php if ($scoreData["submission"] == 1): ?>
                    <i class="fa fa-pencil" aria-hidden="true"></i>
                    <!-- data-toggle="tooltip" title="Submission" -->
                <?php elseif ($scoreData["submission"] == 2): ?>
                    <i class="fa fa-gamepad" aria-hidden="true"></i>
                <?php endif; ?>
            </div>
            <a href="/changelog?profileNumber=<?=$player?>&chamber=<?=$GLOBALS["chamberID"]?>" class="score"><?= Leaderboard::convertToTime($scoreData["score"]) ?></a>
            <div class="date" date="<?=$scoreData["date"]?>"></div>
            <div class="demo-url">
                <?php if ($scoreData["hasDemo"] == 1): ?>
                    <a href="/getDemo?id=<?=$scoreData["changelogId"] ?>">
                        <i class="fa fa-download" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="youtube">
                <?php if ($scoreData["youtubeID"] != NULL): ?>
                    <i <?php if ($scoreData["youtubeID"] == NULL): ?>
                        style="display:none"
                    <?php else : ?>
                        onclick="embedOnBody('<?=$scoreData["youtubeID"]?>', '#<?=$scoreData["playerRank"]?> - <?=Leaderboard::convertToTime($scoreData["score"])?> - <?=Util::escapeQuotesHTML($playerData["boardname"])?>');"
                    <?php endif; ?>
                        class="youtubeEmbedButton fa fa-youtube-play" aria-hidden="true"></i>
                <?php endif; ?>
            </div>
            <div class="comment">
                <?php if ($scoreData["note"] != NULL): ?>
                    <i class="fa fa-comment" aria-hidden="true"
                       data-container="body" data-toggle="popover" data-placement="top"
                       data-content="<?=$scoreData["note"]?>">
                    </i>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
