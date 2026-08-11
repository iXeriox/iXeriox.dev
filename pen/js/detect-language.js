function detectLanguage(code)
{

    const text = code.toLowerCase().trim();



    // PHP
    if(
        text.includes("<?php") ||
        text.includes("<?=") ||
        text.includes("echo ") ||
        text.includes("$this")
    )
    {
        return "php";
    }



    // Vue
    if(
        text.includes("<template") ||
        text.includes("<script setup") ||
        text.includes("defineprops") ||
        text.includes("defineemits")
    )
    {
        return "html";
    }



    // React JSX
    if(
        text.includes("react") ||
        text.includes("jsx") ||
        text.includes("usestate(") ||
        text.includes("useeffect(") ||
        text.includes("return (")
    )
    {
        return "javascript";
    }



    // TypeScript
    if(
        text.includes("interface ") ||
        text.includes("type ") ||
        text.includes(": string") ||
        text.includes(": number") ||
        text.includes("typescript")
    )
    {
        return "typescript";
    }



    // JSON
    if(
        (
            text.startsWith("{") &&
            text.endsWith("}")
        )
        ||
        text.includes('"version":')
        ||
        text.includes('"name":')
    )
    {
        return "json";
    }



    // JavaScript
    if(
        text.includes("const ") ||
        text.includes("let ") ||
        text.includes("var ") ||
        text.includes("function ") ||
        text.includes("=>") ||
        text.includes("console.log")
    )
    {
        return "javascript";
    }



    // HTML/XML
    if(
        text.includes("<html") ||
        text.includes("<body") ||
        text.includes("<div") ||
        text.includes("</")
    )
    {
        return "html";
    }



    // CSS / SCSS
    if(
        (
            text.includes("{") &&
            text.includes("}")
        )
        &&
        (
            text.includes("color:")
            ||
            text.includes("display:")
            ||
            text.includes("margin:")
            ||
            text.includes("padding:")
            ||
            text.includes("font-size:")
        )
    )
    {
        if(
            text.includes("$")
            ||
            text.includes("@mixin")
        )
        {
            return "scss";
        }


        return "css";
    }



    // Python
    if(
        text.includes("def ") ||
        text.includes("import ") ||
        text.includes("print(") ||
        text.includes("elif ")
    )
    {
        return "python";
    }



    // Java
    if(
        text.includes("public class") ||
        text.includes("system.out.println")
    )
    {
        return "java";
    }



    // C#
    if(
        text.includes("using system;") ||
        text.includes("namespace ")
    )
    {
        return "csharp";
    }



    // C / C++
    if(
        text.includes("#include") ||
        text.includes("std::") ||
        text.includes("cout ")
    )
    {
        return "cpp";
    }



    // Go
    if(
        text.includes("package main") ||
        text.includes("func main")
    )
    {
        return "go";
    }



    // Rust
    if(
        text.includes("fn main") ||
        text.includes("let mut") ||
        text.includes("cargo")
    )
    {
        return "rust";
    }



    // SQL
    if(
        text.includes("select ") ||
        text.includes("insert ") ||
        text.includes("update ") ||
        text.includes("delete ") ||
        text.includes("create table")
    )
    {
        return "sql";
    }



    // Bash
    if(
        text.startsWith("#!/bin/bash") ||
        text.includes("sudo ") ||
        text.includes("chmod ") ||
        text.includes("apt ")
    )
    {
        return "shell";
    }



    // PowerShell
    if(
        text.includes("get-command") ||
        text.includes("$env:") ||
        text.includes("write-host")
    )
    {
        return "powershell";
    }



    // YAML
    if(
        text.includes("version:")
        ||
        text.includes("services:")
        ||
        text.includes("docker:")
    )
    {
        return "yaml";
    }



    // Dockerfile
    if(
        text.startsWith("from ")
        ||
        text.includes("run apt")
        ||
        text.includes("copy ")
    )
    {
        return "dockerfile";
    }



    // Markdown
    if(
        text.startsWith("# ")
        ||
        text.includes("## ")
        ||
        text.includes("```")
    )
    {
        return "markdown";
    }



    // Lua
    if(
        text.includes("function ")
        &&
        text.includes("end")
    )
    {
        return "lua";
    }



    // Kotlin
    if(
        text.includes("fun main")
        ||
        text.includes("val ")
    )
    {
        return "kotlin";
    }



    // Swift
    if(
        text.includes("import foundation")
        ||
        text.includes("func ")
    )
    {
        return "swift";
    }



    // Ruby
    if(
        text.includes("def ")
        &&
        text.includes("end")
    )
    {
        return "ruby";
    }



    return "plaintext";

}