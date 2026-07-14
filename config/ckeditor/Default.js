return {
  alignment: {
    options: [
      'left',
      'center',
      'right'
    ],
  },
  code: {
    indentSequence: '  ',
  },
  fontColor: {
    colors: [
      {
        color: '#ABAF0C',
        hasBorder: false,
        label: 'Green',
      },
    ],
  },
  heading: {
    options: [
      {
        class: 'faux-p',
        model: 'paragraph',
        title: 'Paragraph',
      },
      {
        class: 'faux-h2',
        model: 'heading2',
        title: 'Heading 2',
        view: 'h2',
      },
      {
        class: 'faux-h3',
        model: 'heading3',
        title: 'Heading 3',
        view: 'h3',
      },
      {
        class: 'faux-h4',
        model: 'heading4',
        title: 'Heading 4',
        view: 'h4',
      },
      {
        class: 'faux-h5',
        model: 'heading5',
        title: 'Heading 5',
        view: 'h5',
      },
      {
        class: 'faux-h6',
        model: 'heading6',
        title: 'Heading 6',
        view: 'h6',
      },
    ],
  },
  link: {
    "decorators": {
      "isExternal": {
        "attributes": {
          "rel": "noopener noreferrer",
          "target": "_blank"
        },
        "label": "Open in new tab",
        "mode": "manual"
      }
    }
  },
  "list": {
    "properties": {
      "styles": false
    }
  },
  style: {
    definitions: [
      {
        classes: [
          "faux-h1"
        ],
        element: "h2",
        name: "H1 Heading"
      },
      {
        classes: [
          "faux-h3"
        ],
        element: "h2",
        name: "H3 Heading"
      },
      {
        classes: [
          "faux-h4"
        ],
        element: "h2",
        name: "H4 Heading"
      },
      {
        classes: [
          'g-p-icon',
        ],
        element: 'p',
        name: 'Icon',
      },
      {
        classes: [
          'faux-p-lg',
        ],
        element: 'p',
        name: 'Large Paragraph',
      },
      {
        classes: [
          'faux-p-sm',
        ],
        element: 'p',
        name: 'Small Paragraph',
      },
      {
        classes: [
          'faux-p-xs',
        ],
        element: 'p',
        name: 'XS Small Paragraph',
      },
    ],
  },
}