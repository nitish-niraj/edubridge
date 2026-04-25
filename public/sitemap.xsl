<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
  <xsl:output method="html" encoding="UTF-8" indent="yes"/>

  <xsl:template match="/">
    <html lang="en">
      <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <title>EduBridge Sitemap</title>
        <style>
          body { margin: 0; font-family: Segoe UI, Arial, sans-serif; background: #f8fbff; color: #102a43; }
          .wrap { max-width: 980px; margin: 40px auto; padding: 0 16px; }
          h1 { margin: 0 0 10px; font-size: 28px; color: #0d3b66; }
          p { margin: 0 0 18px; color: #486581; }
          table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(16,42,67,0.08); }
          th, td { padding: 12px 14px; border-bottom: 1px solid #e5edf5; text-align: left; font-size: 14px; }
          th { background: #f1f6fb; font-weight: 700; color: #1f3f5b; }
          tr:last-child td { border-bottom: none; }
          a { color: #0d3b66; text-decoration: none; }
          a:hover { text-decoration: underline; }
          .mono { font-family: Consolas, Menlo, monospace; font-size: 13px; }
        </style>
      </head>
      <body>
        <div class="wrap">
          <h1>EduBridge Sitemap</h1>
          <p>Public URLs available for indexing.</p>
          <table>
            <thead>
              <tr>
                <th>URL</th>
                <th>Change Frequency</th>
                <th>Priority</th>
              </tr>
            </thead>
            <tbody>
              <xsl:for-each select="s:urlset/s:url">
                <tr>
                  <td class="mono">
                    <a href="{s:loc}"><xsl:value-of select="s:loc"/></a>
                  </td>
                  <td><xsl:value-of select="s:changefreq"/></td>
                  <td><xsl:value-of select="s:priority"/></td>
                </tr>
              </xsl:for-each>
            </tbody>
          </table>
        </div>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
