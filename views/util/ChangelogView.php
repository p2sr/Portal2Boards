<?php

class ChangelogView
{
    static $lastDate = NULL;
    static $oddDateEntry = false;

    static function getEntry($board, $key, $page, $entry) { ?>
        <?php $val = $board[$key] ?>
        <div class="entry" <?php
            if (strtotime($val["time_gained"]) != strtotime(self::$lastDate)) {
                self::$oddDateEntry = !self::$oddDateEntry;
            }
            if(!self::$oddDateEntry) { ?>
                style="background: #d6d6d6"<?php
            }
            self::$lastDate = $val["time_gained"];
        ?>>
            <div class="date" date="<?=$val["time_gained"]?>"><div class='dateTime'></div><div class='dateDifference'></div></div>
            <div class="profileIcon">
                <a href="/profile/<?=$val["profile_number"]?>">
                    <?php if ($val["avatar"] != NULL && $page == 1): ?>
                        <img src="<?=$val["avatar"]; ?>" alt=""/>
                    <?php elseif ($val["avatar"] != NULL && $page != 1): ?>
                        <img src="" avatar="<?=$val["avatar"]; ?>" alt=""/>
                    <?php else: ?>
                        <img src="" alt=""/>
                    <?php endif; ?>
                </a>
            </div>
            <div class="boardname"><a href="/profile/<?=$val["profile_number"]?>"><?=$val["player_name"]?></a></div>
            <div class="map"><a href="/chamber/<?=$val["mapid"]?>"><?=$val["chamberName"]?></a></div>
            <div class="chapter"><a href="/aggregated/chapter/<?=$val["chapterId"]?>"><?=$GLOBALS["mapInfo"]["chapters"][$val["chapterId"]]["chapterName"]?></a></div>
            <div class="previousscore">
                <?php if ($val["pre_rank"] != NULL): ?>
                    <div class="rank"><?=$val["pre_rank"]?></div>
                <?php else: ?>
                    <div class="rank">-</div>
                <?php endif; ?>
                <?php if ($val["previous_score"] != NULL): ?>
                    <a href="/changelog?profileNumber=<?=$val["profile_number"]?>&chamber=<?=$val["mapid"]?>" class="time"><?=Leaderboard::convertToTime($val["previous_score"])?></a>
                <?php else: ?>
                    <div class="time">-</div>
                <?php endif; ?>
            </div>
            <div class="newscore">
                <?php if ($val["post_rank"] != NULL): ?>
                        <div class="rank"><?=$val["post_rank"]?></div>
                    <?php else: ?>
                        <div class="rank">-</div>
                    <?php endif; ?>
                    <?php if ($val["score"] != NULL): ?>
                        <a class="time" href="/changelog?profileNumber=<?=$val["profile_number"]?>&chamber=<?=$val["mapid"]?>"><?=Leaderboard::convertToTime($val["score"])?></a>
                    <?php else: ?>
                        <div class="time">-</div>
                    <?php endif; ?>
            </div>
            <div class="improvement">
                <?php if($val["rank_improvement"] != null):
                        if($val["rank_improvement"] < 0): ?>
                            <div class="rankImprovement">+<?=abs($val["rank_improvement"])?></div>
                        <?php else: ?>
                            <div class="rankImprovement">-<?=abs($val["rank_improvement"]);?></div>
                        <?php endif;?>
                    <?php else: ?>
                        <div class="rankImprovement">-</div>
                    <?php endif; ?>
                <?php if($val["improvement"] != null): ?>
                    <div class="time"><?=($val["improvement"] < 0) ? "+" . Leaderboard::convertToTime($val["improvement"]) : "-" . Leaderboard::convertToTime($val["improvement"]);?></div>
                <?php else: ?>
                    <div class="time">-</div>
                <?php endif; ?>
            </div>
            <div class="demo-url">
                <?php if ($val["hasDemo"] == 1): ?>
                    <a href="/getDemo?id=<?=$val["id"]?>">
                        <i class="fa fa-download" aria-hidden="true"></i>
                    </a>
                <?php endif; ?></div>
            <div class="youtube">
                    <i <?php if ($val["youtubeID"] == NULL): ?>
                        style="display:none"
                    <?php else : ?>
                        onclick="embedOnBody('<?=$val["youtubeID"]?>', '<?=$val["chamberName"]?> - <?=Leaderboard::convertToTime($val["score"])?> - <?=Util::escapeQuotesHTML($val["player_name"])?>');" class="youtubeEmbedButton fa fa-youtube-play"
                    <?php endif; ?>
                        aria-hidden="true">
                    </i>
            </div>
            <div class="youtube">
                    <i <?php if ($val["autorender_id"] !== NULL): ?>
                            onclick="window.open('https://autorender.portal2.sr/videos/<?=$val["autorender_id"]?>','_blank')" class="youtubeEmbedButton fa fa-play" title="Auto Render"
                        <?php else: ?>
                            style="display:none"
                        <?php endif; ?>
                        aria-hidden="true">
                    </i>
            </div>
            <div class="comment">
                <?php if ($val["note"] != NULL): ?>
                    <i class="fa fa-comment" aria-hidden="true" data-toggle='popover' data-content="<?=$val["note"]?>"></i>
                <?php endif; ?>
            </div>
            <div class="submission">
                <?php if ($val["submission"] == 1): ?>
                    <i class="fa fa-pencil" aria-hidden="true" data-toggle="tooltip" title="Submission"></i>
                <?php elseif ($val["submission"] == 2): ?>
                    <i class="fa fa-gamepad" aria-hidden="true" data-toggle="tooltip" title="Autosubmission"></i>
                <?php endif; ?>
            </div>
            <?php if ($val["pending"] == 1): ?>
            <div class="submission">
                <i class="fa fa-hourglass" aria-hidden="true" data-toggle="tooltip" title="Pending - evidence required"></i>
            </div>
            <?php endif; ?>
            <div class="banScore" >
                <?php if (SteamSignIn::loggedInUserIsAdmin()): ?>
                    <div class="setBannedStatus unban" style="<?php if ($val["banned"] == 0): ?> display: none <?php endif; ?>">
                        <i class="fa fa-check" aria-hidden="true" data-toggle="tooltip" title="Unban" style="cursor: pointer;" onclick="setBannedStatus(<?=$val["id"]?>, 0, event.target)"></i>
                    </div>
                    <div class="setBannedStatus ban" style="<?php if ($val["banned"] == 1): ?> display: none <?php endif; ?>">
                        <i class="fa fa-ban" aria-hidden="true" data-toggle="tooltip" title="Ban" style="cursor: pointer;" onclick="setBannedStatus(<?=$val["id"]?>, 1, event.target)"></i>
                    </div>
                    <div class="status" style="display: none"></div>
                <?php else: ?>
                    <?php if ($val["banned"] == 1): ?>
                        <div class ="banIndicator">
                            <i class="fa fa-exclamation-triangle" aria-hidden="true" data-toggle="tooltip" title="Banned"></i>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }
}
