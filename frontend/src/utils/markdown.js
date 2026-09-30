import MarkdownIt from 'markdown-it'
import DOMPurify from 'dompurify'

const md = new MarkdownIt({ html: false, linkify: true, breaks: true, typographer: false })

// Render GitHub style task lists
md.core.ruler.after('inline', 'task-lists', (state) => {
  state.tokens.forEach((token, i) => {
    if (token.type !== 'inline' || !token.children?.length) return
    const first = token.children[0]
    const m = /^\[( |x|X)\]\s+/.exec(first.content || '')
    if (m && state.tokens[i - 1]?.type === 'paragraph_open' && state.tokens[i - 2]?.type === 'list_item_open') {
      first.content = first.content.slice(m[0].length)
      const cb = new state.Token('html_inline', '', 0)
      cb.content = `<input type="checkbox" disabled ${m[1] !== ' ' ? 'checked' : ''}> `
      token.children.unshift(cb)
    }
  })
})

export function renderMarkdown(text) {
  return DOMPurify.sanitize(md.render(text || ''), { ADD_ATTR: ['target'] })
}
