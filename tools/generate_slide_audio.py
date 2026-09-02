#!/usr/bin/env python3
import asyncio
import re
import sys
import unicodedata
import xml.etree.ElementTree as ET
from pathlib import Path
from zipfile import ZipFile

import edge_tts


NS = {
    "draw": "urn:oasis:names:tc:opendocument:xmlns:drawing:1.0",
    "presentation": "urn:oasis:names:tc:opendocument:xmlns:presentation:1.0",
    "text": "urn:oasis:names:tc:opendocument:xmlns:text:1.0",
}


def clean_text(value: str) -> str:
    value = value.replace("\u00a0", " ").replace("\t", " ")
    value = re.sub(r"\s+", " ", value)
    return value.strip()


def paragraph_texts(element):
    return [
        clean_text("".join(paragraph.itertext()))
        for paragraph in element.findall(".//text:p", NS)
        if clean_text("".join(paragraph.itertext()))
    ]


def visible_paragraphs(page):
    note_tag = f"{{{NS['presentation']}}}notes"
    paragraph_tag = f"{{{NS['text']}}}p"
    result = []

    def visit(element):
        if element.tag == note_tag:
            return
        if element.tag == paragraph_tag:
            value = clean_text("".join(element.itertext()))
            if value:
                result.append(value)
            return
        for child in element:
            visit(child)

    visit(page)
    return result


def slide_title(index, visible):
    if index in (8, 17):
        return visible[1]
    if index == 27:
        return visible[0]
    if len(visible) >= 2:
        return f"{visible[0]} — {visible[1]}"
    return visible[0]


def notes_text(page):
    notes = page.find("./presentation:notes", NS)
    if notes is None:
        return ""
    parts = paragraph_texts(notes)
    value = " ".join(parts)
    value = re.sub(
        r"^Durée cible\s*:\s*\d+\s*(?:minutes|minute|secondes|seconde)"
        r"(?:\s*\d+\s*secondes?)?\s*\.?\s*",
        "",
        value,
        flags=re.IGNORECASE,
    )
    value = re.sub(r"\s*\[Sources\].*$", "", value, flags=re.DOTALL)
    return clean_text(value)


def safe_filename(value):
    ascii_value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode()
    ascii_value = re.sub(r"[^A-Za-z0-9]+", "_", ascii_value).strip("_")
    return ascii_value[:72]


async def generate_one(number, title, notes, destination):
    spoken = f"Diapositive {number}. {title}. {notes}"
    filename = destination / f"{number:02d}_{safe_filename(title)}.mp3"
    if not filename.exists():
        communicator = edge_tts.Communicate(
            spoken,
            voice="fr-FR-DeniseNeural",
            rate="+3%",
            pitch="+1Hz",
            volume="+0%",
        )
        await communicator.save(str(filename))
    return filename, spoken


async def main(source, destination):
    destination.mkdir(parents=True, exist_ok=True)
    with ZipFile(source) as archive:
        root = ET.fromstring(archive.read("content.xml"))
    pages = root.findall(".//draw:page", NS)
    if len(pages) != 27:
        raise RuntimeError(f"27 diapositives attendues, {len(pages)} trouvées")

    records = []
    for number, page in enumerate(pages, 1):
        visible = visible_paragraphs(page)
        title = slide_title(number, visible)
        notes = notes_text(page)
        if not notes:
            raise RuntimeError(f"Notes absentes pour la diapositive {number}")
        filename, spoken = await generate_one(number, title, notes, destination)
        records.append((number, title, filename.name, spoken))
        print(f"Créé : {filename.name}", flush=True)

    transcript = destination / "transcriptions.txt"
    transcript.write_text(
        "\n\n".join(
            f"DIAPOSITIVE {number} — {title}\nFichier : {filename}\n\n{spoken}"
            for number, title, filename, spoken in records
        )
        + "\n",
        encoding="utf-8",
    )


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit("Usage: generate_slide_audio.py SOURCE.odp DESTINATION")
    asyncio.run(main(Path(sys.argv[1]), Path(sys.argv[2])))
