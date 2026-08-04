<?php

class DemoValidator {
    const parserPath = ROOT_PATH . "/util/demo_validator/parse_demo.ts";

    public static function validateManualSubmission(string $demoPath, string $chamber, int $score): array {
        $metadata = self::parseDemo($demoPath);

        if ($metadata === null) {
            return self::valid("Demo metadata parser unavailable.");
        }

        if (isset($metadata["error"])) {
            return self::invalid("Could not validate demo metadata: " . $metadata["error"]);
        }

        $parsedScore = self::getExpectedScore($metadata, $chamber);
        if ($parsedScore !== null && intval($parsedScore) !== $score) {
            return self::invalid(
                "Demo time does not match submitted time. Demo is " .
                Leaderboard::convertToTime(intval($parsedScore)) .
                ", submitted time is " .
                Leaderboard::convertToTime($score) . "."
            );
        }

        $parsedChambers = self::getChambersFromMapName($metadata["mapName"] ?? null);
        if ($parsedChambers !== null && !in_array($chamber, $parsedChambers, true)) {
            $maps = Cache::get("maps");
            $parsedChamber = $parsedChambers[0];
            $demoMap = $maps["maps"][$parsedChamber]["mapName"] ?? $parsedChamber;
            $submittedMap = $maps["maps"][$chamber]["mapName"] ?? $chamber;

            return self::invalid(
                "Demo map does not match submitted chamber. Demo is " .
                $demoMap . ", submitted chamber is " . $submittedMap . "."
            );
        }

        return self::valid("Demo metadata matched submission.");
    }

    public static function previewManualSubmission(string $demoPath, string $chamber): array {
        $metadata = self::parseDemo($demoPath);

        if ($metadata === null) {
            return self::invalid("Demo metadata parser unavailable.");
        }

        if (isset($metadata["error"])) {
            return self::invalid("Could not validate demo metadata: " . $metadata["error"]);
        }

        $maps = Cache::get("maps");
        $parsedChambers = self::getChambersFromMapName($metadata["mapName"] ?? null);
        $parsedChamber = $parsedChambers[0] ?? null;
        $demoMap = $parsedChamber !== null
            ? ($maps["maps"][$parsedChamber]["mapName"] ?? $parsedChamber)
            : ($metadata["mapName"] ?? null);
        $submittedMap = $maps["maps"][$chamber]["mapName"] ?? $chamber;
        $mapMatches = $parsedChambers === null || in_array($chamber, $parsedChambers, true);
        $score = self::getExpectedScore($metadata, $chamber);

        return [
            "valid" => true,
            "mapName" => $metadata["mapName"] ?? null,
            "demoMap" => $demoMap,
            "submittedMap" => $submittedMap,
            "mapMatches" => $mapMatches,
            "score" => $score,
            "formattedScore" => $score === null ? null : Leaderboard::convertToTime(intval($score)),
        ];
    }

    private static function parseDemo(string $demoPath): ?array {
        if (!file_exists(self::parserPath)) {
            Debug::log("Demo validator parser not found: " . self::parserPath);
            return null;
        }

        $deno = self::findDeno();
        if ($deno === null) {
            Debug::log("Deno not found; skipping demo validation.");
            return null;
        }

        $denoDir = ROOT_PATH . "/cache/deno";
        if (!is_dir($denoDir)) {
            mkdir($denoDir, 0775, true);
        }

        $cmd = "DENO_DIR=" . escapeshellarg($denoDir) . " " .
            escapeshellarg($deno) .
            " run --quiet --allow-read=" . escapeshellarg($demoPath) .
            " --allow-net=jsr.io " .
            "--config " . escapeshellarg(ROOT_PATH . "/util/demo_validator/deno.json") . " " .
            escapeshellarg(self::parserPath) . " " .
            escapeshellarg($demoPath);

        $stdout = null;
        $stderr = null;
        $exitCode = self::runCommand($cmd, $stdout, $stderr);

        if ($exitCode !== 0) {
            Debug::log("Demo validator failed: " . $stderr);
            return ["error" => trim($stderr) ?: "unknown parser error"];
        }

        $metadata = json_decode($stdout, true);
        return is_array($metadata) ? $metadata : ["error" => "invalid parser output"];
    }

    private static function findDeno(): ?string {
        foreach (["deno", "/usr/local/bin/deno", "/usr/bin/deno"] as $candidate) {
            $output = null;
            $stderr = null;
            if (self::runCommand(escapeshellarg($candidate) . " --version", $output, $stderr) === 0) {
                return $candidate;
            }
        }

        return null;
    }

    private static function getExpectedScore(array $metadata, string $chamber): ?int {
        $maps = Cache::get("maps");
        $isPortalChamber = isset($maps["lpMaps"][$chamber]);
        $score = $isPortalChamber ? ($metadata["portalScore"] ?? null) : ($metadata["timeScore"] ?? null);

        return $score === null ? null : intval($score);
    }

    private static function getChambersFromMapName(?string $mapName): ?array {
        if ($mapName === null) {
            return null;
        }

        $mapIds = require ROOT_PATH . "/util/demo_validator/portal2_map_ids.php";
        $ids = $mapIds[$mapName] ?? null;
        if ($ids === null) {
            return null;
        }

        return array_values(array_filter([$ids["time"], $ids["portals"]]));
    }

    private static function valid(string $message): array {
        return ["valid" => true, "message" => $message];
    }

    private static function invalid(string $message): array {
        return ["valid" => false, "message" => $message];
    }

    private static function runCommand(string $cmd, &$stdout=null, &$stderr=null): int {
        $proc = proc_open($cmd, [
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ], $pipes, ROOT_PATH);

        if (!is_resource($proc)) {
            return -1;
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return proc_close($proc);
    }
}