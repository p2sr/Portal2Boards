import { assert, assertEquals } from "jsr:@std/assert";

const API = "https://board.portal2.local";
const PROFILE = Deno.env.get('STEAM_ID')!;
const COOKIE = `PHPSESSID=${Deno.env.get('PHPSESSID')}`;

Deno.test("Manual submission", async (t) => {
  let id = "";

  await t.step("Submit change", async () => {
    const body = new FormData();
    body.append("profileNumber", PROFILE);
    body.append("chamber", "47458");
    body.append("score", "2300");
    body.append("youtubeID", "DM3a55hXiI0");
    body.append(
      "demoFile",
      new Blob([await Deno.readFile("./tests/data/short.dem")]),
    );
    body.append("comment", "test");

    const res = await fetch(`${API}/submitChange`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    const run = await res.json();
    assert(typeof run === "object");

    assert(run.player_name);
    assert(run.avatar);
    assertEquals(run.profile_number, PROFILE);
    assertEquals(run.score, 2300);
    assert(run.id);
    assert(run.pre_rank);
    assertEquals(run.post_rank, 5);
    assertEquals(run.wr_gain, 0);
    assert(run.time_gained);
    assertEquals(run.hasDemo, 1);
    assertEquals(run.youtubeID, "DM3a55hXiI0");
    assertEquals(run.note, "test");
    assertEquals(run.banned, 0);
    assertEquals(run.submission, 1);
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

  await t.step("Delete comment", async () => {
    const body = new FormData();
    body.append("id", id);

    const res = await fetch(`${API}/deleteComment`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
  });

  await t.step("Set comment", async () => {
    const body = new FormData();
    body.append("id", id);
    body.append("comment", "test 123");

    const res = await fetch(`${API}/setComment`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
  });

  await t.step("Delete YouTube ID", async () => {
    const body = new FormData();
    body.append("id", id);

    const res = await fetch(`${API}/deleteYoutubeID`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
  });

  await t.step("Set YouTube ID", async () => {
    const body = new FormData();
    body.append("id", id);
    body.append("youtubeID", "DM3a55hXiI0");

    const res = await fetch(`${API}/setYoutubeID`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
  });

  await t.step("Delete demo", async () => {
    const body = new FormData();
    body.append("id", id);

    const res = await fetch(`${API}/deleteDemo`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
  });

  await t.step("Upload demo", async () => {
    const body = new FormData();
    body.append("id", id);
    body.append(
      "demoFile",
      new Blob([await Deno.readFile("./tests/data/short.dem")]),
    );

    const res = await fetch(`${API}/uploadDemo`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
  });

  await t.step("Run mdp", async () => {
    const res = await fetch(`${API}/runMdp?id=${id}`, {
      method: "GET",
      headers: {
        "Cookie": COOKIE,
      },
    });

    assertEquals(res.status, 200);

    assertEquals(
      await res.text(),
      `<code style="white-space: pre">demo: '/var/www/html/demos/PortalGun_2300_76561198049848090_${id}.dem'\n` +
        `\t'NeKz' on sp_a2_triple_laser - 60.00 TPS - 79 ticks\n` +
        `\tevents:\n` +
        `\tno checksums found; vanilla demo?\n` +
        `</code>`,
    );
  });

  await t.step("Set score ban status", async () => {
    const body = new FormData();
    body.append("id", "82802");
    body.append("banStatus", "1");

    const res = await fetch(`${API}/setScoreBanStatus`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
  });

  await t.step("Verify score", async () => {
    const body = new FormData();
    body.append("id", "82802");

    const res = await fetch(`${API}/verifyScore`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
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

Deno.test("Set profile ban status", async (t) => {
  await t.step("Ban", async () => {
    const body = new FormData();
    body.append("profileNumber", PROFILE);
    body.append("banStatus", "1");

    const res = await fetch(`${API}/setProfileBanStatus`, {
      method: "POST",
      headers: {
        "Cookie": COOKIE,
      },
      body,
    });

    assertEquals(res.status, 200);

    await res.body?.cancel();
  });

  await t.step("Unban", async () => {
    const body = new FormData();
    body.append("profileNumber", PROFILE);
    body.append("banStatus", "0");

    const res = await fetch(`${API}/setProfileBanStatus`, {
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

Deno.test("Edit profile", async () => {
  const body = new FormData();
  body.append("youtube", "/@NeKz");
  body.append("twitch", "NeKzor");
  body.append("boardname", "NeKz");

  const res = await fetch(`${API}/editprofile`, {
    method: "POST",
    headers: {
      "Cookie": COOKIE,
    },
    body,
  });

  assertEquals(res.status, 200);

  await res.body?.cancel();
});

Deno.test("Regenerate auth hash", async () => {
  const res = await fetch(`${API}/regenerateAuthHash`, {
    method: "POST",
    headers: {
      "Cookie": COOKIE,
    },
  });

  assertEquals(res.status, 200);

  await res.body?.cancel();
});

Deno.test("Fetch new user data", async () => {
  const body = new FormData();
  body.append("profileNumber", PROFILE);

  const res = await fetch(`${API}/fetchNewUserData`, {
    method: "POST",
    headers: {
      "Cookie": COOKIE,
    },
    body,
  });

  assertEquals(res.status, 200);

  await res.body?.cancel();
});
