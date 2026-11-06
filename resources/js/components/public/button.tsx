import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const buttonStyles = cva(
    'inline-flex items-center justify-center gap-2 rounded-control text-center font-body font-medium transition-[background-color,color,border-color,scale] duration-150 ease-out [-webkit-tap-highlight-color:transparent] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]',
    {
        variants: {
            variant: {
                primary: 'bg-cobalt text-cream hover:bg-cobalt-deep',
                outline:
                    'border border-ink text-ink hover:bg-ink hover:text-cream',
                light: 'bg-cream text-ink hover:bg-sand',
            },
            size: {
                default: 'min-h-12 px-5 text-sm',
                sm: 'h-10 px-3.5 text-xs',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'default',
        },
    },
);

type ButtonProps = ComponentProps<'button'> &
    VariantProps<typeof buttonStyles> & {
        asChild?: boolean;
    };

export function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: ButtonProps) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="public-button"
            className={cn(buttonStyles({ variant, size }), className)}
            {...props}
        />
    );
}
