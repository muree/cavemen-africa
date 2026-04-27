export const siteConfig = {
  name: "Cavemen Africa",
  /** Header logo in /public — replace with your full wordmark PNG/WebP if needed. */
  logoSrc: "/cavemen-logo.svg",
  legalName: "CAVEMEN IMPACT SOLUTIONS LTD",
  tagline: "Studio of Studios",
  description:
    "Cavemen is an innovative studio that offers a collaborative workforce for executing creative services including content creation.",
  url: process.env.NEXT_PUBLIC_SITE_URL ?? "https://cavemen.africa",
  contactEmail: "info@cavemen.africa",
  phoneDisplay: "09134434065",
  phoneTel: "+2349134434065",
  addressLines: [
    "No 2 Guda Abdullahi Road, Farm Center",
    "Kano, Nigeria",
  ],
  social: {
    facebook: "https://www.facebook.com/share/16CNzBvkPs/?mibextid=wwXIfr",
    x: "https://x.com/Cavemenafrica",
    instagram: "https://www.instagram.com/cavemenafrica",
    whatsapp: "https://whatsapp.com/channel/0029Vb71e85KgsNyR43vd61J",
    youtube: "https://youtube.com/@cavemenafrica?si=_KkfyOYYurXxESDz",
    tiktok: "http://tiktok.com/@cavemen.africa",
  },
} as const;
