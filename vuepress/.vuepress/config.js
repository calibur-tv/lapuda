module.exports = {
  title: 'calibur.tv - 天下漫友是一家',
  description: 'Just playing around',
  head: [
    ['link', { rel: 'icon', href: 'https://static.calibur.tv/favicon.ico' }]
  ],
  port: 8088,
  base: '/Laputa/',
  dest: 'docs',
  themeConfig: {
    sidebar: [
      '/',
      ['/api/v1/', '接口文档']
    ]
  }
}
