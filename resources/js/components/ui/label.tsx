import * as LabelPrimitive from "@radix-ui/react-label"
import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Penanda field form:
 * - `required` → tanda <span class="require">*</span> merah (lihat app.css).
 * - `optional` → teks "(opt)" abu-abu.
 * Field yang bukan input (checkbox "Remember me", filter, dsb.) tidak perlu keduanya.
 */
function Label({
  className,
  children,
  required = false,
  optional = false,
  ...props
}: React.ComponentProps<typeof LabelPrimitive.Root> & {
  required?: boolean
  optional?: boolean
}) {
  return (
    <LabelPrimitive.Root
      data-slot="label"
      className={cn(
        "text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        className
      )}
      {...props}
    >
      {children}
      {required && (
        <span className="require" aria-hidden="true">
          *
        </span>
      )}
      {optional && (
        <>
          {" "}
          <span className="font-normal text-muted-foreground">(opt)</span>
        </>
      )}
    </LabelPrimitive.Root>
  )
}

export { Label }
