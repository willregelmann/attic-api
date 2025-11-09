#!/usr/bin/env python3
"""
Download Power Rangers logos, generate thumbnails, and update Supabase database
"""

import requests
import io
import uuid
from PIL import Image
import psycopg2
from urllib.parse import urlparse

# Supabase configuration
SUPABASE_URL = "http://localhost:54321"
# Using service_role key for admin access
SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZS1kZW1vIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImV4cCI6MTk4MzgxMjk5Nn0.EGIM96RAZx35lJzdJsyH-qQwv8Hdp7fsn3W0YpN81IU"

# Database configuration
DB_CONFIG = {
    "host": "localhost",
    "port": 54322,
    "database": "postgres",
    "user": "postgres",
    "password": "postgres",
    "sslmode": "disable"
}

# Logo URLs and collection IDs
LOGOS = [
    {
        "id": "2b826f9e-3858-4a78-bb2a-f061831fb056",
        "name": "Power Rangers Operation Overdrive",
        "url": "https://static.wikia.nocookie.net/logopedia/images/b/b2/Power_Rangers_Operation_Overdrive_S15_logo_2007.png"
    },
    {
        "id": "6bdc683d-4573-4aee-b7a9-7a60341a8596",
        "name": "Power Rangers Megaforce",
        "url": "https://static.wikia.nocookie.net/logopedia/images/3/36/Logo-prm.png"
    },
    {
        "id": "43f76b93-6324-4c78-b5e9-fdd0f2fb68de",
        "name": "Power Rangers Ninja Steel",
        "url": "https://static.wikia.nocookie.net/logopedia/images/0/07/Ninjasteellogo2.png"
    },
    {
        "id": "38247dfa-5d59-4a5a-a96e-b25bddd689fd",
        "name": "Power Rangers Dino Thunder",
        "url": "https://static.wikia.nocookie.net/logopedia/images/3/36/Power_Rangers_Dino_Thunder_Logo.png"
    },
    {
        "id": "a49e05ea-7354-47a0-8632-3bd7d8b45c2c",
        "name": "Power Rangers Samurai/Super Samurai",
        "url": "https://static.wikia.nocookie.net/logopedia/images/d/d8/Power_Rangers_Samurai_Logo.png"
    },
    {
        "id": "20319cc5-5461-4f26-a0cd-a5db0842aca9",
        "name": "Power Rangers Dino Charge",
        "url": "https://static.wikia.nocookie.net/logopedia/images/c/c6/Dinocharge.png"
    },
    {
        "id": "c4743bfa-a670-459c-bbc4-1016686a8e2a",
        "name": "Power Rangers Wild Force",
        "url": "https://static.wikia.nocookie.net/logopedia/images/b/b9/Power_Rangers_Wild_Force_Logo.png"
    },
    {
        "id": "de6f30df-0b7b-4f08-9bbd-daad3a44e8bf",
        "name": "Power Rangers Mystic Force",
        "url": "https://static.wikia.nocookie.net/logopedia/images/d/dd/Power_Rangers_Mystic_Force_Logo.png"
    },
    {
        "id": "9f724eef-ed1e-4bff-946e-e9e77bc31580",
        "name": "Power Rangers S.P.D.",
        "url": "https://static.wikia.nocookie.net/logopedia/images/7/7c/Power_Rangers_SPD_Logo.png"
    },
    {
        "id": "25ff7a34-2188-47fa-8820-53ac2e20fc7b",
        "name": "Power Rangers RPM",
        "url": "https://static.wikia.nocookie.net/logopedia/images/2/27/Power_Rangers_RPM_Logo.png"
    },
    {
        "id": "81ddffd4-9eda-4198-8ff2-d6342518cedf",
        "name": "Power Rangers Jungle Fury",
        "url": "https://static.wikia.nocookie.net/logopedia/images/2/2c/Power_Rangers_Jungle_Fury_Logo.png"
    },
    {
        "id": "43b26c88-aa16-402c-b201-381d690c9719",
        "name": "Power Rangers Time Force",
        "url": "https://static.wikia.nocookie.net/logopedia/images/9/9d/Power_Rangers_Time_Force_Logo.png"
    },
    {
        "id": "2f702210-5c38-4db5-8e75-891d86794036",
        "name": "Power Rangers Ninja Storm",
        "url": "https://static.wikia.nocookie.net/logopedia/images/3/36/Power_Rangers_Ninja_Storm_Logo.png"
    },
    {
        "id": "cf968bae-4353-4e54-95b1-87f41e5f9994",
        "name": "Power Rangers Toys",
        "url": "https://static.wikia.nocookie.net/logopedia/images/7/71/MMPR_Era_Logo.png"
    },
]


def download_image(url):
    """Download image from URL"""
    print(f"  Downloading from {url}")
    response = requests.get(url, headers={"User-Agent": "Mozilla/5.0"})
    response.raise_for_status()
    return Image.open(io.BytesIO(response.content))


def create_thumbnail(image, max_size=400):
    """Create thumbnail preserving aspect ratio and transparency"""
    # Convert to RGBA if not already
    if image.mode != 'RGBA':
        image = image.convert('RGBA')

    # Calculate new size preserving aspect ratio
    image.thumbnail((max_size, max_size), Image.Resampling.LANCZOS)
    return image


def upload_to_supabase(image_data, filename, content_type='image/png'):
    """Upload image to Supabase storage"""
    headers = {
        "Authorization": f"Bearer {SUPABASE_KEY}",
        "Content-Type": content_type
    }

    url = f"{SUPABASE_URL}/storage/v1/object/images/{filename}"
    response = requests.post(url, data=image_data, headers=headers)

    if response.status_code in [200, 201]:
        # Return relative path, not full URL
        return f"/storage/v1/object/public/images/{filename}"
    else:
        print(f"  Upload failed: {response.status_code} - {response.text}")
        raise Exception(f"Upload failed: {response.text}")


def update_database(collection_id, image_url, thumbnail_url):
    """Update database with new image URLs"""
    conn = psycopg2.connect(**DB_CONFIG)
    cur = conn.cursor()

    try:
        cur.execute("""
            UPDATE entities
            SET image_url = %s, thumbnail_url = %s
            WHERE id = %s
        """, (image_url, thumbnail_url, collection_id))

        conn.commit()
        print(f"  Database updated successfully")
    except Exception as e:
        conn.rollback()
        print(f"  Database update failed: {e}")
        raise
    finally:
        cur.close()
        conn.close()


def process_logo(logo_data):
    """Process a single logo: download, thumbnail, upload, update DB"""
    print(f"\nProcessing: {logo_data['name']}")

    try:
        # Download original image
        image = download_image(logo_data['url'])

        # Generate unique filenames
        image_id = str(uuid.uuid4())
        original_filename = f"originals/{image_id}.png"
        thumbnail_filename = f"{image_id}.png"

        # Save original to bytes
        original_bytes = io.BytesIO()
        image.save(original_bytes, format='PNG', optimize=True)
        original_bytes.seek(0)

        # Check if original is too large (> 5MB), compress if needed
        original_size = len(original_bytes.getvalue())
        if original_size > 5 * 1024 * 1024:
            print(f"  Original too large ({original_size / 1024 / 1024:.1f}MB), compressing...")
            # Create a smaller version (max 1500px)
            compressed = image.copy()
            compressed.thumbnail((1500, 1500), Image.Resampling.LANCZOS)
            original_bytes = io.BytesIO()
            compressed.save(original_bytes, format='PNG', optimize=True)
            original_bytes.seek(0)
            print(f"  Compressed to {len(original_bytes.getvalue()) / 1024 / 1024:.1f}MB")

        # Create and save thumbnail
        thumbnail = create_thumbnail(image.copy())
        thumbnail_bytes = io.BytesIO()
        thumbnail.save(thumbnail_bytes, format='PNG', optimize=True)
        thumbnail_bytes.seek(0)

        # Upload both to Supabase
        print(f"  Uploading original...")
        image_url = upload_to_supabase(original_bytes.getvalue(), original_filename)

        print(f"  Uploading thumbnail...")
        thumbnail_url = upload_to_supabase(thumbnail_bytes.getvalue(), thumbnail_filename)

        # Update database
        print(f"  Updating database...")
        update_database(logo_data['id'], image_url, thumbnail_url)

        print(f"  ✓ Complete!")
        print(f"    Image: {image_url}")
        print(f"    Thumb: {thumbnail_url}")

    except Exception as e:
        print(f"  ✗ Error: {e}")


def main():
    print("Power Rangers Logo Updater")
    print("=" * 50)
    print(f"Processing {len(LOGOS)} logos...\n")

    for logo in LOGOS:
        process_logo(logo)

    print("\n" + "=" * 50)
    print("Done!")


if __name__ == "__main__":
    main()
