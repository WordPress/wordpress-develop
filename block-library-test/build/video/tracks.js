"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Tracks;
var _react = require("react");
function Tracks({
  tracks = []
}) {
  return tracks.map(track => {
    return (0, _react.createElement)("track", {
      key: track.src,
      ...track
    });
  });
}
//# sourceMappingURL=tracks.js.map