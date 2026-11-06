import { cn } from '@/lib/utils';

type CoverImageProps = {
    src: string;
    alt?: string;
    className?: string;
    imgClassName?: string;
    loading?: 'eager' | 'lazy';
};

// Wrapper memegang rasio; gambar selalu object-cover agar tidak gepeng.
export function CoverImage({
    src,
    alt = '',
    className,
    imgClassName,
    loading = 'lazy',
}: CoverImageProps) {
    return (
        <div
            className={cn('relative overflow-hidden bg-sand-light', className)}
        >
            <img
                src={src}
                alt={alt}
                loading={loading}
                decoding="async"
                className={cn(
                    'absolute inset-0 size-full object-cover',
                    imgClassName,
                )}
            />
        </div>
    );
}
