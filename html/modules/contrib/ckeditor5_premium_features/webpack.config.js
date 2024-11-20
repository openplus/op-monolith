/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

const path = require('path');
const glob = require('glob');
const webpack = require('webpack');
const { styles, builds } = require('@ckeditor/ckeditor5-dev-utils');
const TerserPlugin = require('terser-webpack-plugin');

const entries = glob.sync('./{modules/*/js,js}/ckeditor5_plugins/**/*.js').reduce((entries, entry) => {
    const entryName = path.parse(entry).name
    if (entryName !== 'index') {
      entries[entryName] = entry.replace(entryName + '/src/' + entryName + '.js', '')
    }

    return entries
  }, {});

module.exports = [];
// // Loop through every subdirectory in src, each a different plugin, and build
// // each one in ./build.
Object.entries(entries).forEach((mapping) => {
  const name = mapping[0];
  const dir = mapping[1];

  const bc = {
    mode: 'production',
    optimization: {
      minimize: true,
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            output: {
              comments: /^!/
            }
          },
          test: /\.js(\?.*)?$/i,
          extractComments: false,
        }),
      ],
      moduleIds: 'named',
    },
    entry: {
      path: dir + `${name}/src/index.js`,
    },
    output: {
      path: path.resolve(dir, '../build'),
      filename: `${name}.js`,
      library: ['CKEditor5', name],
      libraryTarget: 'umd',
      libraryExport: 'default',
    },
    plugins: [
      new webpack.BannerPlugin("Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.\nFor licensing, see https://ckeditor.com/legal/ckeditor-oss-license"),
      new webpack.DllReferencePlugin({
        manifest: require('./node_modules/ckeditor5/build/ckeditor5-dll.manifest.json'), // eslint-disable-line global-require, import/no-unresolved
        scope: 'ckeditor5/src',
        name: 'CKEditor5.dll',
      }),
    ],
    module: {
      rules: [
        { test: /\.svg$/, use: 'raw-loader' },
        { test: /\.css$/, use: [ 'style-loader', 'css-loader' ] }
      ],
    },
  };

  module.exports.push(bc);
});
