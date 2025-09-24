# Storage Setup for Railway Deployment

Since Railway deployments are ephemeral (files are lost on redeploy), you need to set up persistent storage for uploaded images. Here are your options:

## Option 1: Cloudflare R2 (Recommended - Free tier available)

Cloudflare R2 is S3-compatible storage with no egress fees and a generous free tier (10GB storage, 10 million requests/month).

### Setup Steps:

1. **Create Cloudflare R2 Bucket**
   - Go to [Cloudflare Dashboard](https://dash.cloudflare.com/)
   - Navigate to R2
   - Create a new bucket (e.g., `attic-images`)
   - Enable public access for the bucket

2. **Generate API Credentials**
   - In R2, go to "Manage R2 API tokens"
   - Create new API token with Object Read & Write permissions
   - Save the Access Key ID and Secret Access Key

3. **Configure Railway Environment Variables**
   Add these to your Railway service:
   ```
   FILESYSTEM_DISK=r2
   R2_ACCESS_KEY_ID=your_access_key_id
   R2_SECRET_ACCESS_KEY=your_secret_access_key
   R2_BUCKET=attic-images
   R2_ENDPOINT=https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com
   R2_URL=https://your-public-bucket-url.r2.dev
   ```

4. **Install AWS S3 SDK**
   The Laravel app needs the S3 package:
   ```bash
   composer require league/flysystem-aws-s3-v3 "^3.0"
   ```

## Option 2: AWS S3

Traditional S3 storage (costs for storage and bandwidth).

### Setup Steps:

1. **Create S3 Bucket**
   - Go to AWS Console → S3
   - Create new bucket
   - Configure public access settings
   - Set up CORS if needed

2. **Create IAM User**
   - Create user with S3 access
   - Attach policy for bucket access
   - Generate access keys

3. **Configure Railway Environment Variables**
   ```
   FILESYSTEM_DISK=s3
   AWS_ACCESS_KEY_ID=your_access_key_id
   AWS_SECRET_ACCESS_KEY=your_secret_access_key
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=your-bucket-name
   AWS_URL=https://your-bucket.s3.amazonaws.com
   ```

## Option 3: Supabase Storage

Supabase offers storage with their PostgreSQL database service.

### Setup Steps:

1. **Enable Storage in Supabase**
   - Go to your Supabase project
   - Navigate to Storage
   - Create a new bucket for images

2. **Get API Credentials**
   - Find your project URL and anon key
   - Configure storage policies for public access

3. **Implement Custom Storage Driver**
   You'll need to create a custom Laravel storage driver for Supabase.

## Option 4: Keep Local (Development Only)

For development/testing, you can use local storage, but images will be lost on each Railway deploy.

No additional configuration needed, just ensure:
```
FILESYSTEM_DISK=public
```

## Testing Your Configuration

After setting up storage, test it:

1. Upload an image through the admin panel
2. Check if the image persists after a redeploy
3. Verify the image URL is accessible

## Troubleshooting

### Images not showing after deploy
- Check environment variables are set correctly
- Verify bucket permissions allow public read
- Check Laravel logs for storage errors

### Upload fails
- Verify credentials are correct
- Check bucket exists and is writable
- Ensure file size limits aren't exceeded

### CORS Issues
- Configure CORS on your storage bucket
- Add your domain to allowed origins

## Recommended: Cloudflare R2

For this project, R2 is recommended because:
- Free tier is generous for small projects
- No bandwidth/egress charges
- S3-compatible API (works with existing code)
- Global CDN included
- Easy to set up

The code is already configured to support R2 - just add the environment variables!