import Link from "next/link";
import { siteConfig } from "@/lib/site-config";

const navLinkClass =
  "rounded-md px-3 py-2 text-sm font-medium text-[var(--color-ink-muted)] transition-colors hover:bg-[var(--color-sand)] hover:text-[var(--color-ink)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-terracotta)]";

export function SiteHeader() {
  return (
    <header className="border-b border-[var(--color-border)] bg-[var(--color-cream)]/90 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <Link
          href="/"
          className="flex items-center gap-2.5 rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-terracotta)]"
        >
          <img
            src={siteConfig.logoSrc}
            alt=""
            width={40}
            height={40}
            className="h-9 w-9 shrink-0 object-contain"
            decoding="async"
          />
          <span className="font-serif text-lg font-semibold tracking-tight text-[var(--color-ink)]">
            {siteConfig.name}
          </span>
        </Link>
        <nav aria-label="Primary" className="flex flex-wrap items-center gap-1 sm:gap-2">
          <Link href="/" className={navLinkClass}>
            Home
          </Link>
          <Link href="/asali" className={navLinkClass}>
            Asali Poetry Sessions
          </Link>
          <Link href="/kanti" className={navLinkClass}>
            Kanti
          </Link>
        </nav>
      </div>
    </header>
  );
}
