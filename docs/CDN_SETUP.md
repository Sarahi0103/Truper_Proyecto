# CDN Configuration for Images - Truper Platform

## Overview
This document explains how to configure a CDN (Content Delivery Network) for serving product images in the Truper Platform to improve performance and reduce server load.

## Recommended CDN Providers

### 1. Cloudflare (Recommended - Free Tier Available)
- **Free tier**: Unlimited bandwidth
- **Easy setup**: DNS-based configuration
- **Image optimization**: Built-in Polish feature
- **Global edge network**: 200+ locations

### 2. AWS CloudFront
- **Pay-as-you-go**: Pay only for usage
- **AWS integration**: Works well with S3
- **Advanced features**: Lambda@Edge, custom origins
- **Cost**: ~$0.085/GB for data transfer out

### 3. Cloudinary
- **Image optimization**: Automatic format conversion (WebP, AVIF)
- **Dynamic transformations**: Resize, crop, filters on-the-fly
- **Free tier**: 25GB/month bandwidth
- **Cost**: $89/month for 100GB/month

## Implementation Steps

### Option 1: Cloudflare (Simplest)

1. **Sign up for Cloudflare**
   - Go to https://cloudflare.com
   - Create a free account
   - Add your domain

2. **Configure DNS**
   - Point your domain's DNS to Cloudflare
   - Enable "Proxied" (orange cloud) for image URLs

3. **Enable Image Optimization**
   - Go to Speed > Optimization
   - Enable "Auto Image Optimization"
   - Enable "Resizing" if needed

4. **Update Application Code**
   ```php
   // In config/config.php or image serving logic
   function get_image_url($imagePath) {
       $cdnDomain = 'https://cdn.yourdomain.com';
       return $cdnDomain . '/' . ltrim($imagePath, '/');
   }
   ```

### Option 2: AWS CloudFront + S3

1. **Create S3 Bucket**
   ```bash
   aws s3 mb s3://truper-images
   aws s3 sync public/images/ s3://truper-images/
   ```

2. **Create CloudFront Distribution**
   - Origin: S3 bucket
   - Cache behavior: Optimize for images
   - TTL: 86400 (24 hours)

3. **Update Application Code**
   ```php
   function get_image_url($imagePath) {
       $cdnDomain = 'https://d1234567890.cloudfront.net';
       return $cdnDomain . '/' . ltrim($imagePath, '/');
   }
   ```

### Option 3: Cloudinary

1. **Sign up for Cloudinary**
   - Go to https://cloudinary.com
   - Create a free account

2. **Upload Images**
   ```php
   require 'vendor/autoload.php';
   use Cloudinary\Cloudinary;
   
   $cloudinary = new Cloudinary([
       'cloud_name' => 'your-cloud-name',
       'api_key' => 'your-api-key',
       'api_secret' => 'your-api-secret'
   ]);
   
   $cloudinary->uploadApi()->upload('path/to/image.jpg', [
       'folder' => 'products',
       'transformation' => [
           'quality' => 'auto',
           'fetch_format' => 'auto'
       ]
   ]);
   ```

3. **Update Application Code**
   ```php
   function get_image_url($imagePath) {
       $cloudName = 'your-cloud-name';
       return "https://res.cloudinary.com/$cloudName/image/upload/$imagePath";
   }
   ```

## Configuration in Truper Platform

### Step 1: Add CDN Configuration to .env
```env
# CDN Configuration
CDN_ENABLED=true
CDN_DOMAIN=https://cdn.yourdomain.com
CDN_IMAGE_PATH=/images/products
CDN_CACHE_TTL=86400
```

### Step 2: Update Image Resolution Logic
In `config/image_cache.php` or wherever images are served:

```php
function get_cdn_image_url($imagePath) {
    if (getenv('CDN_ENABLED') === 'true') {
        $cdnDomain = getenv('CDN_DOMAIN');
        $imagePath = ltrim($imagePath, '/');
        return $cdnDomain . '/' . $imagePath;
    }
    return $imagePath; // Serve locally if CDN disabled
}
```

### Step 3: Update Product Display
In `public/index.php` or product listing pages:

```php
$productImage = get_cdn_image_url($product['image_url']);
```

## Testing CDN Configuration

1. **Test individual image**
   ```bash
   curl -I https://cdn.yourdomain.com/images/products/product.jpg
   ```

2. **Check cache headers**
   - Look for `X-Cache: HIT` or `MISS`
   - Verify `Cache-Control` header

3. **Monitor performance**
   - Use browser DevTools Network tab
   - Compare load times with/without CDN

## Migration Strategy

### Phase 1: Setup (Week 1)
- Set up CDN provider
- Configure DNS
- Upload existing images to CDN

### Phase 2: Testing (Week 2)
- Enable CDN for test environment
- Verify image loading
- Test cache invalidation

### Phase 3: Rollout (Week 3)
- Enable CDN for production
- Monitor performance metrics
- Roll back if issues occur

## Cache Invalidation

### Cloudflare
```bash
# Purge single file
curl -X POST "https://api.cloudflare.com/client/v4/zones/ZONE_ID/purge_cache" \
  -H "Authorization: Bearer API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{"files":["https://cdn.yourdomain.com/image.jpg"]}'
```

### CloudFront
```bash
# Create invalidation
aws cloudfront create-invalidation \
  --distribution-id E1234567890 \
  --paths "/images/*"
```

### Cloudinary
```php
$cloudinary->uploadApi()->explicit('product.jpg', [
    'type' => 'upload',
    'invalidate' => true
]);
```

## Cost Estimation

### Cloudflare (Free Tier)
- **Cost**: $0/month
- **Bandwidth**: Unlimited
- **Requests**: Unlimited
- **Limitations**: No custom SSL on free tier

### AWS CloudFront
- **Cost**: ~$0.085/GB data transfer
- **Example**: 100GB/month = $8.50/month
- **S3 storage**: ~$0.023/GB/month

### Cloudinary
- **Free tier**: 25GB/month bandwidth
- **Paid**: $89/month for 100GB/month
- **Best for**: Image optimization and transformations

## Monitoring

### Key Metrics to Track
- **Cache hit ratio**: Should be >80%
- **Image load time**: Should be <500ms
- **Bandwidth usage**: Monitor CDN costs
- **Error rate**: Should be <0.1%

### Tools
- Cloudflare Analytics
- AWS CloudWatch
- Cloudinary Dashboard
- Google PageSpeed Insights

## Troubleshooting

### Images not loading
- Check CDN domain configuration
- Verify DNS propagation
- Check SSL certificate

### Cache not working
- Verify cache headers
- Check CDN cache settings
- Test with curl -I

### High costs
- Review bandwidth usage
- Optimize image sizes
- Consider image compression

## Security Considerations

1. **Access Control**
   - Restrict CDN access to your domain
   - Use signed URLs for private images
   - Enable HTTPS only

2. **Hotlink Protection**
   - Configure referer checking
   - Use token-based authentication
   - Monitor unauthorized access

3. **SSL/TLS**
   - Enable HTTPS for CDN
   - Use valid SSL certificates
   - Configure HSTS headers

## Rollback Plan

If CDN causes issues:

1. **Disable CDN in .env**
   ```env
   CDN_ENABLED=false
   ```

2. **Clear application cache**
   ```bash
   rm -rf cache/*
   ```

3. **Restart application**
   ```bash
   php -S localhost:8000
   ```

## Conclusion

Implementing a CDN for images will significantly improve:
- **Page load speed**: 50-80% faster image loading
- **Server performance**: Reduced CPU and bandwidth usage
- **User experience**: Faster image rendering
- **Scalability**: Handle traffic spikes better

**Recommendation**: Start with Cloudflare free tier for simplicity and cost-effectiveness.
