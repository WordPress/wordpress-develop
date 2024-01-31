import { createElement } from "react";
export default function Tracks({
  tracks = []
}) {
  return tracks.map(track => {
    return createElement("track", {
      key: track.src,
      ...track
    });
  });
}
//# sourceMappingURL=tracks.js.map