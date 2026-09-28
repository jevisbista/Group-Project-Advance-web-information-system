# Asset credits

The current interface uses six locally stored real photographs and the locally hosted Inter font. There are no remote image or font requests at runtime.

## Photographs

The Home hero, About page, login page, club highlight and event cards use optimized JPEG photographs from Pexels. The full photographer credits, source pages, original downloads, dimensions and file sizes are recorded in [photo-sources.md](photo-sources.md). The photographs were downloaded under the [Pexels License](https://www.pexels.com/license/).

These are representative stock scenes, not photographs of actual ClubConnect members or past events. The About page explains this without adding image captions. Committee cards retain initials rather than using unrelated portraits. The requested demo banner and decorative labels have been removed; the fictional club facts and seeded event records remain labelled in their relevant content.

| File | Use |
| --- | --- |
| `public/assets/images/hero.jpg` | Students studying together, Home hero |
| `public/assets/images/about.jpg` | Friends walking with coffee, About |
| `public/assets/images/workshop.jpg` | Laptop collaboration, workshops |
| `public/assets/images/community.jpg` | Cafe conversation, social events, login and default image |
| `public/assets/images/games.jpg` | Friends around a board game, games events |
| `public/assets/images/volunteer.jpg` | Group gardening, volunteering events |

`app/events.php` maps event titles to the local JPG files and uses `community.jpg` for an unmatched title or a missing selected image. Image markup declares dimensions, uses meaningful alternative text and lazy-loads below-fold images. The original SVG scene files are retained as unused project source assets; displayed scene images now use photographs.

## Font

[Inter](https://rsms.me/inter/) by Rasmus Andersson is hosted locally in `public/assets/fonts/InterVariable.woff2`. It supplies normal variable weights 100 through 900 and is applied throughout the site, including inherited form controls. Its original [SIL Open Font License 1.1](../public/assets/fonts/LICENSE.txt) and official download sources are included alongside the font. CSS uses `font-display: swap` and the shared head preloads the local font. No external font service is required.

## Brand and interface icons

The original ClubConnect wordmark symbol, small location icons and SVG/PNG favicon remain as crisp interface graphics. They were created for this project and have no third-party attribution requirement. The PNG favicon is 32 x 32 pixels. The shared head supplies the favicon on every normal page.
