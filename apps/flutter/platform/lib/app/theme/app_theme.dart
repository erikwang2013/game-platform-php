// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';

const _dataTableTheme = DataTableThemeData(
  dataRowMinHeight: 48,
  dataRowMaxHeight: 48,
  headingRowHeight: 40,
  headingTextStyle: TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
  dataTextStyle: TextStyle(fontSize: 13),
);

const _cardTheme = CardThemeData(
  elevation: 1,
  shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(8))),
  margin: EdgeInsets.zero,
);

const _inputDecorationTheme = InputDecorationTheme(
  border: OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(6))),
  contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
  isDense: true,
);

const _dividerTheme = DividerThemeData(space: 0, thickness: 1);

class AppTheme {
  static final ThemeData light = ThemeData(
    useMaterial3: true,
    colorSchemeSeed: const Color(0xFF1677FF),
    brightness: Brightness.light,
    dataTableTheme: _dataTableTheme,
    cardTheme: _cardTheme,
    inputDecorationTheme: _inputDecorationTheme,
    dividerTheme: _dividerTheme,
  );

  static final ThemeData dark = ThemeData(
    useMaterial3: true,
    colorSchemeSeed: const Color(0xFF1677FF),
    brightness: Brightness.dark,
    dataTableTheme: _dataTableTheme,
    cardTheme: _cardTheme,
    inputDecorationTheme: _inputDecorationTheme,
    dividerTheme: _dividerTheme,
  );
}
