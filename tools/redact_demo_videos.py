#!/usr/bin/python3
"""Create jury-safe copies of the Mon Allure demo recordings."""

import shutil
import sys
from pathlib import Path

import gi

gi.require_version("Gst", "1.0")
from gi.repository import Gst  # noqa: E402


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = ROOT / "output" / "vidéos"
OUTPUT_DIR = SOURCE_DIR / "anonymisées"


def visitor_boxes(seconds):
    if 6.0 <= seconds < 14.0:
        return [
            (638, 310, 630, 62),   # adresse e-mail
            (638, 505, 630, 66),   # mot de passe
            (638, 604, 630, 66),   # confirmation du mot de passe
        ]
    if 14.0 <= seconds <= 18.0:
        return [
            (670, 580, 565, 60),   # adresse de connexion
            (670, 675, 565, 62),   # mot de passe de connexion
        ]
    return []


def admin_boxes(seconds):
    boxes = []
    if 0.0 <= seconds <= 3.8:
        boxes.append((638, 735, 635, 72))  # adresse de connexion personnelle
    if 7.0 <= seconds < 19.0:
        boxes.extend([
            (470, 418, 355, 56),  # adresse du compte administrateur
            (470, 492, 355, 56),  # adresse personnelle du compte chris
            (470, 566, 355, 56),  # même ligne après changement de filtre
        ])
    if 23.0 <= seconds <= 29.0:
        boxes.extend([
            (470, 738, 355, 56),
            (470, 812, 355, 56),
        ])
    return boxes


def transcode(source, destination, box_provider):
    pipeline = Gst.parse_launch(
        f'filesrc location="{source}" ! qtdemux name=d '
        'd.video_0 ! queue ! decodebin ! videoconvert ! '
        'cairooverlay name=redaction ! videoconvert ! video/x-raw,format=I420 ! '
        'x264enc speed-preset=veryfast tune=zerolatency bitrate=2500 key-int-max=60 ! '
        'queue ! mux.video_0 '
        'd.audio_0 ! queue ! mux.audio_0 '
        f'mp4mux name=mux faststart=true ! filesink location="{destination}"'
    )

    overlay = pipeline.get_by_name("redaction")

    def draw(_overlay, context, timestamp, _duration):
        seconds = timestamp / Gst.SECOND
        for x, y, width, height in box_provider(seconds):
            context.set_source_rgba(0.025, 0.055, 0.15, 0.98)
            context.rectangle(x, y, width, height)
            context.fill()
            context.set_source_rgba(0.10, 0.90, 0.93, 0.9)
            context.set_line_width(3)
            context.rectangle(x, y, width, height)
            context.stroke()

    overlay.connect("draw", draw)
    pipeline.set_state(Gst.State.PLAYING)
    bus = pipeline.get_bus()
    message = bus.timed_pop_filtered(
        Gst.CLOCK_TIME_NONE, Gst.MessageType.ERROR | Gst.MessageType.EOS
    )
    pipeline.set_state(Gst.State.NULL)
    if message.type == Gst.MessageType.ERROR:
        error, debug = message.parse_error()
        raise RuntimeError(f"{error}: {debug}")


def main():
    Gst.init(None)
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    visitor_source = SOURCE_DIR / "login register view compressed.mp4"
    user_source = SOURCE_DIR / "video karim demo utilisateur originale v3 compressed.mp4"
    admin_source = SOURCE_DIR / "video administrator demo originale v1 compressed.mp4"

    transcode(
        visitor_source,
        OUTPUT_DIR / "parcours-visiteur-anonymise.mp4",
        visitor_boxes,
    )
    shutil.copy2(user_source, OUTPUT_DIR / "parcours-utilisateur-anonymise.mp4")
    transcode(
        admin_source,
        OUTPUT_DIR / "parcours-administrateur-anonymise.mp4",
        admin_boxes,
    )


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(f"Erreur: {exc}", file=sys.stderr)
        raise
