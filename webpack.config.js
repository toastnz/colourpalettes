const path = require('path');
const fs = require('fs');
const glob = require('glob');
const webpack = require('webpack');
const postcssUrl = require('postcss-url');
const TerserPlugin = require("terser-webpack-plugin");
const globImporter = require('node-sass-glob-importer');
const postcssCriticalCSS = require('postcss-critical-css');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const { getIfUtils, removeEmpty } = require('webpack-config-utils');
const FriendlyErrorsWebpackPlugin = require('@soda/friendly-errors-webpack-plugin');

const stats = {
  colors: true,
  hash: false,
  version: false,
  timings: false,
  assets: false,
  chunks: false,
  modules: false,
  reasons: false,
  children: false,
  source: false,
  errors: false,
  errorDetails: false,
  warnings: false,
  publicPath: false,
};

// The main directory (outside the webpack folder)
const dir = path.resolve(__dirname, '../');
// Our aliases
const aliases = {
  'styles': path.resolve(__dirname, './client/source/styles/'),
  'scripts': path.resolve(__dirname, './client/source/scripts/'),
};

// Our marmalade config (imports the cms theme, dev theme and blocks to the frontend)
const app = {
  dir,
  files: ['colour-palette-field', 'accessibility'],
  entries: {},
  output: {
    publicPath: '/client/dist/scripts/',
    path: path.resolve(__dirname, './client/dist/scripts'),
    filename: '[name].js',
    chunkFilename: 'components/[chunkhash].js',
  },
  resolve: { alias: aliases },
};

/* Loop through the marmalade entry points and add them to the entries object */
for (let index = 0; index < app.files.length; index++) {
  const file = app.files[index];
  app.entries[file] = path.resolve(__dirname, `./client/source/scripts/${file}.js`);
}

const configs = [];

[app].forEach((config) => {
  configs.push((env, argv) => {
    const { ifProduction } = getIfUtils(argv.mode);

    return {
      mode: ifProduction('production', 'development'),
      stats,
      devtool: 'source-map',
      entry: config.entries,
      output: config.output,
      resolve: config.resolve,
      module: {
        rules: [
          {
            test: /\.js$/,
            exclude: /node_modules/,
            use: [
              { loader: 'babel-loader', options: { sourceMap: ifProduction(false, true) } },
            ],
          },
          {
            test: /\.scss$/,
            use: [
              MiniCssExtractPlugin.loader,
              {
                loader: 'css-loader',
                options: {
                  sourceMap: ifProduction(false, true),
                  url: false,
                }
              },
              {
                loader: 'sass-loader', options: {
                  sourceMap: ifProduction(false, true),
                  sassOptions: {
                    importer: globImporter()
                  },
                }
              },
            ]
          },
        ]
      },
      optimization: {
        minimizer: [
          new TerserPlugin({
            minify: TerserPlugin.uglifyJsMinify,
          }),
          new CssMinimizerPlugin()
        ]
      },
      plugins: removeEmpty([
        new CleanWebpackPlugin(),
        new FriendlyErrorsWebpackPlugin(),
        new MiniCssExtractPlugin({
          filename: '../styles/[name].css',
          chunkFilename: '[name].css'
        }),
      ]),
    }
  });
});

module.exports = configs;
