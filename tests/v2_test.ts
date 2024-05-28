import { assert, assertEquals } from "jsr:@std/assert";

const API = "https://board.portal2.local";
const PROFILE = "76561198049848090";
const AUTH_HASH = "g2SxqP7Iv43Bi3uX4kEJeUUr2IWhf7jg";
const COOKIE = "PHPSESSID=rfaktvssd448o1t93nvnhh187t";

Deno.test("Validate user", async () => {
  const body = new FormData();
  body.append("auth_hash", AUTH_HASH);

  const res = await fetch(`${API}/api-v2/validate-user`, {
    method: "POST",
    body,
  });

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");

  assertEquals(json.userId, PROFILE);
});

Deno.test("Validate user error", async () => {
  const body = new FormData();
  body.append("auth_hash", "ASDF");

  const res = await fetch(`${API}/api-v2/validate-user`, {
    method: "POST",
    body,
  });

  assertEquals(res.status, 400);
  assertEquals(await res.text(), "User validation failed");
});

Deno.test("Active profiles", async () => {
  const body = new FormData();
  body.append("months", "3");

  const res = await fetch(`${API}/api-v2/active-profiles`, {
    method: "POST",
    body,
  });

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");
  assert(typeof json.profiles === "object");
  assert(json.profiles.length > 0);
});

Deno.test("Automatic submission", async (t) => {
  let id = "";

  await t.step("Auto submit", async () => {
    const body = new FormData();
    body.append("auth_hash", AUTH_HASH);
    body.append("mapId", "47458");
    body.append("score", "2300");
    body.append(
      "demoFile",
      new Blob([await Deno.readFile("./tests/data/short.dem")]),
    );
    body.append("comment", "test");

    const res = await fetch(`${API}/api-v2/auto-submit`, {
      method: "POST",
      body,
    });

    assertEquals(res.status, 200);

    const run = await res.json();
    assert(typeof run === "object");

    assertEquals(run.player_name, "NeKz");
    assertEquals(
      run.avatar,
      "https://avatars.steamstatic.com/9a86e6554aee395b3ac37d96a808335363eb79ff_full.jpg",
    );
    assertEquals(run.profile_number, "76561198049848090");
    assertEquals(run.score, 2300);
    assert(run.id);
    assert(run.pre_rank);
    assertEquals(run.post_rank, 5);
    assertEquals(run.wr_gain, 0);
    assert(run.time_gained);
    assertEquals(run.hasDemo, 1);
    assertEquals(run.youtubeID, null);
    assertEquals(run.note, "test");
    assertEquals(run.banned, 0);
    assertEquals(run.submission, 2);
    assertEquals(run.pending, 0);
    assertEquals(run.autorender_id, null);
    assert(run.previous_score);
    assertEquals(run.chamberName, "Portal Gun");
    assertEquals(run.chapterId, 7);
    assertEquals(run.mapid, "47458");
    assert(run.improvement >= 0);
    assert(run.rank_improvement >= 0);
    assertEquals(run.pre_points, null);
    assertEquals(run.post_point, null);
    assertEquals(run.point_improvement, null);

    id = run.id.toString();
  });

  await t.step("Get demo", async () => {
    const res = await fetch(`${API}/getDemo?id=${id}`);

    assertEquals(res.status, 200);

    const demo = await res.arrayBuffer();
    assertEquals(new TextDecoder().decode(demo.slice(0, 8)), "HL2DEMO\0");
  });

  await t.step("Delete submission", async () => {
    const body = new FormData();
    body.append("id", id);

    const res = await fetch(`${API}/deleteSubmission`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
  });
});

Deno.test("Current PB", async () => {
  const body = new FormData();
  body.append("auth_hash", AUTH_HASH);
  body.append("mapId", "62758");

  const res = await fetch(`${API}/api-v2/current-pb`, {
    method: "POST",
    body,
  });

  assertEquals(res.status, 200);

  const pb = await res.json();
  assert(typeof pb === "object");

  assertEquals(pb.time_gained, "2014-03-22T10:36:59Z");
  assertEquals(pb.profile_number, "76561198049848090");
  assertEquals(pb.score, 3006);
  assertEquals(pb.map_id, "62758");
  assertEquals(pb.wr_gain, 0);
  assertEquals(pb.has_demo, 0);
  assertEquals(pb.banned, 0);
  assertEquals(pb.youtube_id, null);
  assertEquals(pb.previous_id, null);
  assertEquals(pb.id, 33541);
  assertEquals(pb.post_rank, 8);
  assertEquals(pb.pre_rank, null);
  assertEquals(pb.submission, 0);
  assertEquals(pb.note, null);
  assertEquals(pb.pending, 0);
  assertEquals(pb.autorender_id, null);
});
