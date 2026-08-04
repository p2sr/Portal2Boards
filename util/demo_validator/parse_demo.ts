import {
  NetMessages,
  ScoreboardTempUpdate,
  SourceDemo,
  SourceDemoParser,
} from "@nekz/sdp";

const [demoPath] = Deno.args;

if (!demoPath) {
  console.error("Missing demo path.");
  Deno.exit(1);
}

try {
  const parser = SourceDemoParser.default();
  const data = await Deno.readFile(demoPath);
  const buffer = parser.prepare(data.buffer);
  const demo = SourceDemo.default();

  demo.readHeader(buffer);

  try {
    demo.readMessages(buffer);
  } catch {
    // Some demos can still expose useful header metadata even when message parsing is partial.
  }

  try {
    demo.readPackets();
  } catch {
    // Keep best-effort metadata; scoreboard may already be available.
  }

  const scoreboard = demo.findPacket<NetMessages.SvcUserMessage>((message) => {
    return message instanceof NetMessages.SvcUserMessage &&
      message.userMessage instanceof ScoreboardTempUpdate;
  });

  const scores = scoreboard?.userMessage?.as<ScoreboardTempUpdate>();

  console.log(JSON.stringify({
    mapName: demo.mapName ?? null,
    gameDirectory: demo.gameDirectory ?? null,
    playbackTime: demo.playbackTime ?? null,
    playbackTicks: demo.playbackTicks ?? null,
    portalScore: scores?.portalScore ?? null,
    timeScore: scores?.timeScore ?? null,
  }));
} catch (err) {
  console.error(err instanceof Error ? err.message : String(err));
  Deno.exit(1);
}
