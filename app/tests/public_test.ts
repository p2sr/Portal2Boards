import { assert, assertEquals } from "jsr:@std/assert";

const API = "https://board.portal2.local";
const PROFILE = "76561198049848090";

Deno.test("Changelog", async () => {
  const res = await fetch(`${API}/changelog/json`);

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");
});

Deno.test("Changelog with parameters", async () => {
  const res = await fetch(
    `${API}/changelog/json?profileNumber=${PROFILE}&startDate=2019-01-01&endDate=2019-02-01&startRank=1&endRank=10`,
  );

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");

  const [entry] = json;
  assert(entry);

  assertEquals(entry.player_name, "NeKz");
  assertEquals(
    entry.avatar,
    "https://avatars.steamstatic.com/9a86e6554aee395b3ac37d96a808335363eb79ff_full.jpg",
  );
  assertEquals(entry.profile_number, "76561198049848090");
  assertEquals(entry.score, 4488);
  assertEquals(entry.id, 91096);
  assertEquals(entry.pre_rank, 6);
  assertEquals(entry.post_rank, 5);
  assertEquals(entry.wr_gain, 0);
  assertEquals(entry.time_gained, "2019-01-02T00:30:04Z");
  assertEquals(entry.hasDemo, 1);
  assertEquals(entry.youtubeID, null);
  assertEquals(entry.note, null);
  assertEquals(entry.banned, 0);
  assertEquals(entry.submission, 0);
  assertEquals(entry.pending, 0);
  assertEquals(entry.previous_score, 4720);
  assertEquals(entry.chamberName, "Repulsion Intro");
  assertEquals(entry.chapterId, 12);
  assertEquals(entry.mapid, "47787");
  assertEquals(entry.improvement, 232);
  assertEquals(entry.rank_improvement, 1);
  assertEquals(entry.pre_points, null);
  assertEquals(entry.post_point, null);
  assertEquals(entry.point_improvement, null);
});

Deno.test("Chamber", async () => {
  const res = await fetch(`${API}/chamber/47458/json`);

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");

  assert(Object.keys(json).length);
});

Deno.test("Profile", async () => {
  const res = await fetch(`${API}/profile/${PROFILE}/json`);

  assertEquals(res.status, 200);

  const profile = await res.json();
  assert(typeof profile === "object");

  assert(profile.points);
  assert(profile.times);
  assert(profile.times.SP);
  assert(profile.times.COOP);
  assertEquals(profile.times.global, null);
  assert(profile.times.chapters);
  assert(profile.userData);

  assertEquals(profile.profileNumber, "76561198049848090");
  assertEquals(profile.isRegistered, null);
  assertEquals(profile.hasRecords, null);
  assertEquals(profile.userData.displayName, "NeKz");
  assertEquals(profile.userData.profile_number, "76561198049848090");
  assertEquals(profile.userData.boardname, "NeKz");
  assertEquals(profile.userData.steamname, "NeKz");
  assertEquals(profile.userData.banned, 0);
  assertEquals(profile.userData.registered, 0);
  assertEquals(
    profile.userData.avatar,
    "https://avatars.steamstatic.com/9a86e6554aee395b3ac37d96a808335363eb79ff_full.jpg",
  );
  assertEquals(profile.userData.twitch, "NeKzor");
  assertEquals(profile.userData.youtube, "/@NeKz");
  assertEquals(profile.userData.title, "Developer");
  assertEquals(profile.userData.admin, 1);
  assertEquals(profile.userData.donation_amount, null);
});

Deno.test("Aggregated overall", async () => {
  const res = await fetch(`${API}/aggregated/overall/json`);

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");
});

Deno.test("Aggregated single player", async () => {
  const res = await fetch(`${API}/aggregated/overall/json`);

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");
});

Deno.test("Aggregated by chapter", async () => {
  const res = await fetch(`${API}/aggregated/chapter/1/json`);

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");
});

Deno.test("Donators", async () => {
  const res = await fetch(`${API}/donators/json`);

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");
});

Deno.test("Wall of Shame", async () => {
  const res = await fetch(`${API}/wallofshame/json`);

  assertEquals(res.status, 200);

  const json = await res.json();
  assert(typeof json === "object");
});

Deno.test("Fetch new chamber scores", async () => {
  const body = new FormData();
  body.append("chamber", "47458");

  const res = await fetch(`${API}/fetchNewChamberScores`, {
    method: "POST",
  });

  assertEquals(res.status, 200);

  await res.body?.cancel();
});
